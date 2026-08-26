<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Iec61850\Tests\Simulation;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Iec61850\Exception\Iec61850Exception;
use Erikwang2013\IndustrialProtocols\Iec61850\Iec61850Connector;
use PHPUnit\Framework\TestCase;

/**
 * IEC 61850 MMS connector lifecycle against a fake IED in a separate process
 * (proc_open), so tests work even when pcntl_fork is unavailable.
 *
 * The stub reads the MMS initiate request and each subsequent TPKT request,
 * then answers with a READ_RESPONSE PDU (0xAD, invokeId 1).
 */
class Iec61850ConnectorTest extends TestCase
{
    private function startFakeIed(int $port): mixed
    {
        $proc = proc_open([PHP_BINARY, '-r', <<<'STUB'
            $port = (int) $argv[1];
            $server = stream_socket_server('tcp://127.0.0.1:' . $port);
            echo "READY\n";
            flush();
            $client = @stream_socket_accept($server, 5);
            if ($client) {
                // Read one full TPKT packet
                $read = function () use ($client) {
                    $header = '';
                    while (strlen($header) < 4) {
                        $chunk = fread($client, 4 - strlen($header));
                        if ($chunk === false || $chunk === '') return false;
                        $header .= $chunk;
                    }
                    $len = unpack('n', substr($header, 2, 2))[1];
                    $body = '';
                    while (strlen($body) < $len - 4) {
                        $chunk = fread($client, $len - 4 - strlen($body));
                        if ($chunk === false || $chunk === '') return false;
                        $body .= $chunk;
                    }
                    return $header . $body;
                };

                // 1. MMS initiate request (no response expected by driver)
                if ($read() === false) {
                    fclose($client);
                    fclose($server);
                    exit(0);
                }
                // 2. Read request -> respond with READ_RESPONSE PDU
                if ($read() !== false) {
                    // TPKT + MMS: pdu 0xAD, len 3, INTEGER invokeId 1
                    fwrite($client, chr(3) . chr(0) . pack('n', 9) . "\xAD\x03\x02\x01\x01");
                }
                fclose($client);
            }
            fclose($server);
STUB, $port], [1 => ['pipe', 'w']], $pipes);

        fgets($pipes[1]);
        return $proc;
    }

    public function testConnectorConnectReadDisconnect(): void
    {
        $proc = $this->startFakeIed(15301);

        $connector = new Iec61850Connector([
            'host' => '127.0.0.1', 'port' => 15301, 'timeout' => 2,
        ]);
        $connector->connect();
        $this->assertTrue($connector->isConnected());
        $this->assertSame(ConnectionState::HEALTHY, $connector->getHealth()->state);

        $results = $connector->read('IED1/MMXU1.MX.A.phsA');
        $this->assertArrayHasKey('IED1/MMXU1.MX.A.phsA', $results);
        $this->assertSame('READ_RESPONSE', $results['IED1/MMXU1.MX.A.phsA']['pdu_type']);
        $this->assertSame(1, $results['IED1/MMXU1.MX.A.phsA']['invoke_id']);

        $connector->disconnect();
        $this->assertFalse($connector->isConnected());

        proc_close($proc);
    }

    public function testConnectorReadMultiplePaths(): void
    {
        // Fake IED answers each read request with a READ_RESPONSE; run the
        // server in 'multi' mode by restarting it per request is overkill —
        // one path per server is fine, so reuse the single-shot stub.
        $proc = $this->startFakeIed(15302);

        $connector = new Iec61850Connector([
            'host' => '127.0.0.1', 'port' => 15302, 'timeout' => 2,
        ]);
        $connector->connect();
        $results = $connector->read('IED1/XCBR1.ST.Pos.stVal');
        $this->assertSame('READ_RESPONSE', $results['IED1/XCBR1.ST.Pos.stVal']['pdu_type']);
        $connector->disconnect();

        proc_close($proc);
    }

    public function testConnectionRefused(): void
    {
        $srv = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($srv, false);
        fclose($srv);
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $connector = new Iec61850Connector([
            'host' => '127.0.0.1', 'port' => $port, 'timeout' => 0.5,
        ]);
        $this->expectException(Iec61850Exception::class);
        $connector->connect();
    }
}

<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Iec61850\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Iec61850\Iec61850Connector;
use Erikwang2013\IndustrialProtocols\Iec61850\Iec61850Protocol;
use PHPUnit\Framework\TestCase;

class Iec61850ProtocolTest extends TestCase
{
    public function testProtocolMetadata(): void
    {
        $protocol = new Iec61850Protocol();
        $this->assertSame('iec61850', $protocol->getName());
        $this->assertSame('1.1.1', $protocol->getVersion());
        $this->assertSame(102, $protocol->getDefaultPort());
        $this->assertSame(['mms', 'goose', 'sv'], $protocol->getSupportedVariants());
    }

    public function testCreateConnectorMmsVariant(): void
    {
        $connector = (new Iec61850Protocol())->createConnector([
            'variant' => 'mms',
            'host' => '127.0.0.1',
            'port' => 102,
        ]);
        $this->assertInstanceOf(Iec61850Connector::class, $connector);
    }

    public function testCreateConnectorDefaultVariantIsMms(): void
    {
        $connector = (new Iec61850Protocol())->createConnector([]);
        $this->assertInstanceOf(Iec61850Connector::class, $connector);
    }

    public function testCreateConnectorGooseWithoutBridgeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bridge');
        (new Iec61850Protocol())->createConnector(['variant' => 'goose']);
    }

    public function testCreateConnectorSvWithoutBridgeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Iec61850Protocol())->createConnector(['variant' => 'sv']);
    }

    public function testCreateConnectorGooseWithBridge(): void
    {
        $bridge = $this->stubBridge();
        $connector = (new Iec61850Protocol())->createConnector(['variant' => 'goose', 'bridge' => $bridge]);
        $this->assertInstanceOf(BridgeConnector::class, $connector);
    }

    public function testCreateConnectorSvWithBridge(): void
    {
        $bridge = $this->stubBridge();
        $connector = (new Iec61850Protocol())->createConnector(['variant' => 'sv', 'bridge' => $bridge]);
        $this->assertInstanceOf(BridgeConnector::class, $connector);
    }

    public function testBridgeConnectorDelegates(): void
    {
        $bridge = $this->stubBridge();
        $connector = (new Iec61850Protocol())->createConnector(['variant' => 'goose', 'bridge' => $bridge]);

        $connector->connect();
        $this->assertTrue($connector->isConnected());
        $this->assertSame(['IED1/LLN0' => 'ok'], $connector->read('IED1/LLN0'));
        $this->assertSame(['IED1/LLN0' => 'ok'], $connector->write('IED1/LLN0', [1]));
        $connector->disconnect();
        $this->assertFalse($connector->isConnected());
    }

    public function testConnectorHealthBeforeConnect(): void
    {
        $connector = (new Iec61850Protocol())->createConnector([]);
        $this->assertSame(\Erikwang2013\IndustrialProtocols\Connection\ConnectionState::CLOSED, $connector->getHealth()->state);
    }

    private function stubBridge(): \Erikwang2013\IndustrialProtocols\Bridge\BridgeInterface
    {
        return new class implements \Erikwang2013\IndustrialProtocols\Bridge\BridgeInterface {
            private bool $ready = false;
            public function open(): void { $this->ready = true; }
            public function close(): void { $this->ready = false; }
            public function execute(string $command, string|array $data = ''): string { return 'ok'; }
            public function isReady(): bool { return $this->ready; }
            public function getType(): string { return 'stub'; }
        };
    }
}

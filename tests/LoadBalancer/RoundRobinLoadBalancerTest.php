<?php

declare(strict_types=1);

namespace App\Tests\LoadBalancer;

use App\LoadBalancer\RoundRobinLoadBalancer;
use PHPUnit\Framework\TestCase;

class RoundRobinLoadBalancerTest extends TestCase
{
    public function testConstructorWithServers(): void
    {
        $servers = ['server1', 'server2', 'server3'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $this->assertEquals($servers, $loadBalancer->servers);
    }

    public function testConstructorWithoutServers(): void
    {
        $loadBalancer = new RoundRobinLoadBalancer();
        
        $this->assertEquals([], $loadBalancer->servers);
    }

    public function testGetNextServerRoundRobin(): void
    {
        $servers = ['server1', 'server2', 'server3'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        $this->assertEquals('server2', $loadBalancer->getNextServer());
        $this->assertEquals('server3', $loadBalancer->getNextServer());
        $this->assertEquals('server1', $loadBalancer->getNextServer());
    }

    public function testGetNextServerWithNoServers(): void
    {
        $loadBalancer = new RoundRobinLoadBalancer();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No servers available');
        
        $loadBalancer->getNextServer();
    }

    public function testAddServer(): void
    {
        $loadBalancer = new RoundRobinLoadBalancer();
        
        $loadBalancer->addServer('server1');
        $this->assertEquals(['server1'], $loadBalancer->servers);
        
        $loadBalancer->addServer('server2');
        $this->assertEquals(['server1', 'server2'], $loadBalancer->servers);
    }

    public function testAddDuplicateServer(): void
    {
        $loadBalancer = new RoundRobinLoadBalancer();
        
        $loadBalancer->addServer('server1');
        $loadBalancer->addServer('server1');
        
        $this->assertEquals(['server1'], $loadBalancer->servers);
    }

    public function testRemoveServer(): void
    {
        $servers = ['server1', 'server2', 'server3'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $loadBalancer->removeServer('server2');
        $this->assertEquals(['server1', 'server3'], $loadBalancer->servers);
    }

    public function testRemoveNonExistentServer(): void
    {
        $servers = ['server1', 'server2'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $loadBalancer->removeServer('server3');
        $this->assertEquals(['server1', 'server2'], $loadBalancer->servers);
    }

    public function testRemoveServerResetsIndex(): void
    {
        $servers = ['server1', 'server2', 'server3'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $loadBalancer->getNextServer();
        $loadBalancer->getNextServer();
        $loadBalancer->removeServer('server3');
        
        $this->assertEquals('server1', $loadBalancer->getNextServer());
    }

    public function testRoundRobinAfterServerModification(): void
    {
        $loadBalancer = new RoundRobinLoadBalancer(['server1']);
        
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        
        $loadBalancer->addServer('server2');
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        $this->assertEquals('server2', $loadBalancer->getNextServer());
        $this->assertEquals('server1', $loadBalancer->getNextServer());
    }
}
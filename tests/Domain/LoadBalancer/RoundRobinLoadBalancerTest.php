<?php

declare(strict_types=1);

namespace App\Tests\Domain\LoadBalancer;

use App\Domain\Exception\NoHealthyServersException;
use App\Domain\LoadBalancer\RoundRobinLoadBalancer;
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

    public function testConstructorRemovesDuplicates(): void
    {
        $servers = ['server1', 'server2', 'server1', 'server3', 'server2'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        $this->assertEquals(['server1', 'server2', 'server3'], $loadBalancer->servers);
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
        
        $this->expectException(NoHealthyServersException::class);
        $this->expectExceptionMessage('No healthy servers available');
        
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
        // With atomic counter, the counter continues from where it left off
        // After first call (counter=1), next call with 2 servers: 1 % 2 = 1 (server2)
        $this->assertEquals('server2', $loadBalancer->getNextServer());
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        $this->assertEquals('server2', $loadBalancer->getNextServer());
    }

    public function testRemoveCurrentServerDoesNotSkip(): void
    {
        $servers = ['server1', 'server2', 'server3'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        // Advance to server2
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        $this->assertEquals('server2', $loadBalancer->getNextServer());
        
        // Remove server3 (next server)
        $loadBalancer->removeServer('server3');
        
        // Should continue with server1, not skip
        $this->assertEquals('server1', $loadBalancer->getNextServer());
        $this->assertEquals('server2', $loadBalancer->getNextServer());
    }

    public function testRemoveAllServersResetsIndex(): void
    {
        $servers = ['server1', 'server2'];
        $loadBalancer = new RoundRobinLoadBalancer($servers);
        
        // Advance index
        $loadBalancer->getNextServer();
        
        // Remove all servers
        $loadBalancer->removeServer('server1');
        $loadBalancer->removeServer('server2');
        
        // Add server back - should start from beginning
        $loadBalancer->addServer('newserver');
        $this->assertEquals('newserver', $loadBalancer->getNextServer());
    }
}
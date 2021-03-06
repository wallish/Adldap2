<?php

namespace Adldap\Tests\Models;

use Adldap\Models\Entry;
use Adldap\Schemas\Schema;
use Adldap\Tests\UnitTestCase;

class EntryTest extends UnitTestCase
{
    protected function newEntryModel($attributes = [], $builder, $schema = null)
    {
        if (is_null($schema)) $schema = Schema::get();

        return new Entry($attributes, $builder, $schema);
    }

    public function test_construct()
    {
        $attributes = [
            'cn'             => 'Common Name',
            'samaccountname' => 'Account Name',
        ];

        $entry = $this->newEntryModel($attributes, $this->newBuilder());

        $this->assertEquals($attributes, $entry->getAttributes());
    }

    public function test_set_raw_attributes()
    {
        $rawAttributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'dn'            => ['dn'],
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$rawAttributes]);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($rawAttributes);

        $this->assertTrue($entry->exists);
        $this->assertEquals($rawAttributes, $entry->getAttributes());
    }

    public function test_set_attribute()
    {
        $attributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$attributes]);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);

        $entry->setCommonName('New Common Name');
        $entry->samaccountname = ['New Account Name'];

        $this->assertEquals('New Common Name', $entry->getCommonName());
        $this->assertEquals(['New Account Name'], $entry->samaccountname);
    }

    public function test_update_attribute()
    {
        $attributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'dn'             => 'dc=corp,dc=org',
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$attributes]);

        $connection->shouldReceive('modReplace')->once()->withArgs(['dc=corp,dc=org', ['cn' => 'John Doe']])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);
        $this->assertTrue($entry->updateAttribute('cn', 'John Doe'));
    }

    public function test_delete_attribute()
    {
        $attributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'dn'             => 'dc=corp,dc=org',
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$attributes]);

        $connection->shouldReceive('modDelete')->once()->withArgs(['dc=corp,dc=org', ['cn' => []]])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);

        $this->assertTrue($entry->deleteAttribute('cn'));
    }

    public function test_create_attribute()
    {
        $attributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'dn'             => 'dc=corp,dc=org',
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$attributes]);

        $connection->shouldReceive('modAdd')->once()->withArgs(['dc=corp,dc=org', ['givenName' => 'John Doe']])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);

        $this->assertTrue($entry->createAttribute('givenName', 'John Doe'));
    }

    public function test_modifications()
    {
        $attributes = [
            'cn'             => ['Common Name'],
            'samaccountname' => ['Account Name'],
            'name'           => ['Name'],
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('read')->once()->andReturn($connection);
        $connection->shouldReceive('getEntries')->once()->andReturn([$attributes]);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);

        $entry->cn = null;
        $entry->samaccountname = 'Changed';
        $entry->test = 'New Attribute';
        $entry->setName('New Name');

        $modifications = $entry->getModifications();

        // Removed 'cn' attribute
        $this->assertEquals('cn', $modifications[0]['attrib']);
        $this->assertFalse(isset($modifications[0]['values']));
        $this->assertEquals(18, $modifications[0]['modtype']);

        // Modified 'samaccountname' attribute
        $this->assertEquals('samaccountname', $modifications[1]['attrib']);
        $this->assertEquals(['Changed'], $modifications[1]['values']);
        $this->assertEquals(3, $modifications[1]['modtype']);

        // Modified 'name' attribute
        $this->assertEquals('name', $modifications[2]['attrib']);
        $this->assertEquals(['New Name'], $modifications[2]['values']);
        $this->assertEquals(3, $modifications[2]['modtype']);

        // New 'test' attribute
        $this->assertEquals('test', $modifications[3]['attrib']);
        $this->assertEquals(['New Attribute'], $modifications[3]['values']);
        $this->assertEquals(1, $modifications[3]['modtype']);
    }

    public function test_create()
    {
        $attributes = [
            'cn' => 'John Doe',
            'givenname' => 'John',
            'sn' => 'Doe',
        ];

        $returnedRaw = [
            'count' => 1,
            [
                'cn' => ['John Doe'],
                'givenname' => ['John'],
                'sn' => ['Doe'],
            ]
        ];

        $connection = $this->newConnectionMock();

        $connection->shouldReceive('add')->withArgs(['cn=John Doe,ou=Accounting,dc=corp,dc=org', $attributes])->andReturn(true);
        $connection->shouldReceive('read')->withArgs(['cn=John Doe,ou=Accounting,dc=corp,dc=org', '(objectclass=*)', []])->andReturn('resource');
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('read')->andReturn($connection);
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('close')->andReturn(true);

        $entry = $this->newEntryModel($attributes, $this->newBuilder($connection));

        $entry->setDn('cn=John Doe,ou=Accounting,dc=corp,dc=org');

        $this->assertTrue($entry->create());
        $this->assertEquals($attributes['cn'], $entry->getCommonName());
        $this->assertEquals($attributes['sn'], $entry->sn[0]);
        $this->assertEquals($attributes['givenname'], $entry->givenname[0]);
    }

    public function test_update()
    {
        $connection = $this->newConnectionMock();

        $dn = 'cn=Testing,ou=Accounting,dc=corp,dc=org';

        $attributes = ['dn' => $dn];

        $connection->shouldReceive('read')->andReturn($connection);
        $connection->shouldReceive('getEntries')->andReturn($attributes);

        $connection->shouldReceive('modifyBatch')->once()->withArgs([$dn, []])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($attributes);

        $this->assertTrue($entry->update());
    }

    public function test_save_for_create()
    {
        $connection = $this->newConnectionMock();

        $attributes = [
            'cn' => 'John Doe',
            'givenname' => 'John',
            'sn' => 'Doe',
        ];

        $dn = 'cn=John Doe,ou=Accounting,dc=corp,dc=org';

        $returnedRaw = [
            'count' => 1,
            [
                'cn' => ['John Doe'],
                'givenname' => ['John'],
                'sn' => ['Doe'],
            ]
        ];

        $connection->shouldReceive('add')->withArgs([$dn, $attributes])->andReturn(true);
        $connection->shouldReceive('read')->withArgs([$dn, '(objectclass=*)', []])->andReturn('resource');
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('read')->andReturn($connection);
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel($attributes, $this->newBuilder($connection));

        $entry->setDn($dn);

        $this->assertTrue($entry->save());
        $this->assertEquals($attributes['cn'], $entry->getCommonName());
        $this->assertEquals($attributes['sn'], $entry->sn[0]);
        $this->assertEquals($attributes['givenname'], $entry->givenname[0]);
    }

    public function test_save_for_update()
    {
        $connection = $this->newConnectionMock();

        $dn = 'cn=Testing,ou=Accounting,dc=corp,dc=org';

        $returnedRaw = [['dn' => $dn]];

        $connection->shouldReceive('read')->andReturn($connection);
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('modifyBatch')->once()->withArgs([$dn, []])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes(['dn' => $dn]);

        $this->assertTrue($entry->save());
    }

    public function test_delete_failure()
    {
        $entry = $this->newEntryModel([], $this->newBuilder());

        $this->setExpectedException('Adldap\Exceptions\AdldapException');

        $entry->delete();
    }

    public function test_delete()
    {
        $connection = $this->newConnectionMock();

        $dn = 'cn=Testing,ou=Accounting,dc=corp,dc=org';

        $returnedRaw = [['dn' => $dn]];

        $connection->shouldReceive('read')->andReturn($connection);
        $connection->shouldReceive('getEntries')->andReturn($returnedRaw);

        $connection->shouldReceive('delete')->once()->withArgs([$dn])->andReturn(true);
        $connection->shouldReceive('close')->once()->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes(['dn' => $dn]);

        $this->assertTrue($entry->delete());
    }

    public function test_convert_string_to_bool()
    {
        $entry = $this->mock('Adldap\Models\Entry')->makePartial();

        $entry->setSchema(Schema::get());

        $entry->shouldAllowMockingProtectedMethods();

        $this->assertNull($entry->convertStringToBool('test'));

        $this->assertTrue($entry->convertStringToBool('true'));
        $this->assertTrue($entry->convertStringToBool('TRUE'));
        $this->assertTrue($entry->convertStringToBool('TRue'));

        $this->assertFalse($entry->convertStringToBool('false'));
        $this->assertFalse($entry->convertStringToBool('FALSE'));
        $this->assertFalse($entry->convertStringToBool('FAlse'));
    }

    public function test_filter_raw_attributes()
    {
        $rawAttributes = [
            'count' => 1,
            'test' => [
                'count' => 1,
                'test' => [
                    'count' => 1,
                    'test' => [
                        'count' => 1,
                        'test' => [
                            'count' => 1
                        ],
                    ]
                ]
            ]
        ];

        $expected = [
            'test' => [
                'test' => [
                    'test' => [
                        'test' => []
                    ]
                ]
            ]
        ];

        $entry = $this->newEntryModel([], $this->newBuilder());

        $entry->setRawAttributes($rawAttributes);

        $this->assertEquals($expected, $entry->getAttributes());
    }

    public function test_move()
    {
        $rawAttributes = [
            'dn' => 'cn=Doe,dc=corp,dc=acme,dc=org',
        ];

        $connection = $this->newConnectionMock();

        $args = [
            'cn=Doe,dc=corp,dc=acme,dc=org',
            'cn=John',
            'ou=Accounts,dc=corp,dc=amce,dc=org',
            true,
        ];

        $connection->shouldReceive('rename')->once()->withArgs($args)->andReturn(true);

        $entry = $this->newEntryModel([], $this->newBuilder($connection));

        $entry->setRawAttributes($rawAttributes);

        $this->assertTrue($entry->move($args[1], $args[2]));
    }

    public function test_dn_is_constructed_when_none_given_and_create_is_called()
    {
        $connection = $this->newConnectionMock();

        $addArgs = [
            'cn=John Doe,dc=corp,dc=local',
            ['cn' => ['John Doe']],
        ];

        $readArgs = [
            'cn=John Doe,dc=corp,dc=local',
            '(objectclass=*)',
            [],
        ];

        $connection->shouldReceive('add')->once()->withArgs($addArgs)->andReturn(true);

        $connection->shouldReceive('read')->once()->withArgs($readArgs)->andReturn(true);

        $connection->shouldReceive('getEntries')->once()->andReturn([]);

        $builder = $this->newBuilder($connection);

        $builder->setDn('DC=corp,DC=local');

        $entry = $this->newEntryModel([], $builder);

        $entry->setCommonName('John Doe');

        $this->assertTrue($entry->create());
    }
}

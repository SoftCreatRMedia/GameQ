<?php

/**
 * This file is part of GameQ.
 *
 * GameQ is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GameQ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace GameQ\Tests;

use ReflectionClass;

/**
 * Protocol test class
 *
 * @package GameQ\Tests
 */
class Protocol extends TestBase
{
    /**
     * Holds stub on setup
     *
     * @var \GameQ\Protocol
     */
    protected \GameQ\Protocol $stub;

    /**
     * Some dummy options
     *
     * @var array<string, string>
     */
    protected array $options = [
        'key1' => 'var1',
    ];

    /**
     * Setup to create our stub
     *
     */
    #[\PHPUnit\Framework\Attributes\Before]
    public function customSetUp(): void
    {
        $this->stub = new class ($this->options) extends \GameQ\Protocol {
            public function processResponse(): array
            {
                return [];
            }
        };
    }

    /**
     * Random assortment of general tests for completeness
     */
    public function testGeneral(): void
    {
        $name = 'Test name';
        $nameLong = 'Test name bigger, longer';
        $portDiff = 5454;

        $reflection = new ReflectionClass($this->stub);
        $reflection_property_name = $reflection->getProperty('name');

        $reflection_property_name->setValue($this->stub, $name);

        self::assertEquals($name, $this->stub->name());

        $reflection_property_namelong = $reflection->getProperty('name_long');

        $reflection_property_namelong->setValue($this->stub, $nameLong);

        self::assertEquals($nameLong, $this->stub->nameLong());

        // Test transport
        self::assertEquals(\GameQ\Protocol::TRANSPORT_UDP, $this->stub->transport());

        // Test transport setting
        $this->stub->transport(\GameQ\Protocol::TRANSPORT_TCP);
        self::assertEquals(\GameQ\Protocol::TRANSPORT_TCP, $this->stub->transport());

        // Test protocol state
        self::assertEquals(\GameQ\Protocol::STATE_STABLE, $this->stub->state());

        // Test port diff
        $reflection_property_portdiff = $reflection->getProperty('port_diff');

        $reflection_property_portdiff->setValue($this->stub, $portDiff);

        self::assertEquals($portDiff, $this->stub->portDiff());
    }

    /**
     * Test packet setter/getter and some other packet methods
     */
    public function testPackets(): void
    {
        $packets = [
            \GameQ\Protocol::PACKET_CHALLENGE => 'Do you even lift?',
            \GameQ\Protocol::PACKET_RULES     => 'There are no rules!!',
        ];

        $reflection = new ReflectionClass($this->stub);
        $reflection_property = $reflection->getProperty('packets');

        $reflection_property->setValue($this->stub, $packets);

        // Test all return
        self::assertEquals($packets, $this->stub->getPacket());

        // Test multiple selected
        self::assertEquals($packets, $this->stub->getPacket([
            \GameQ\Protocol::PACKET_CHALLENGE,
            \GameQ\Protocol::PACKET_RULES,
        ]));

        // Test single selected
        self::assertEquals(
            $packets[\GameQ\Protocol::PACKET_CHALLENGE],
            $this->stub->getPacket(\GameQ\Protocol::PACKET_CHALLENGE),
        );

        // Drop challenge and test for !challenge
        unset($packets[\GameQ\Protocol::PACKET_CHALLENGE]);

        $reflection_property->setValue($this->stub, $packets);

        self::assertEquals($packets, $this->stub->getPacket('!challenge'));

        // test hasChallenge
        self::assertFalse($this->stub->hasChallenge());
        self::assertSame([], $this->stub->getFollowUpPackets());
    }

    /**
     * Test that binary challenges can be replaced repeatedly without format-string parsing
     */
    public function testChallengeApplicationUsesOriginalPacketTemplates(): void
    {
        $protocol = new class extends \GameQ\Protocol {
            protected array $packets = [
                self::PACKET_CHALLENGE => "plain%?packet",
                self::PACKET_RULES => "rules:%s",
            ];

            public function processResponse(): array
            {
                return [];
            }

            public function applyChallenge(string $challenge): bool
            {
                return $this->challengeApply($challenge);
            }
        };

        $protocol->applyChallenge("\x25\xFF");

        self::assertSame(
            [
                \GameQ\Protocol::PACKET_CHALLENGE => "plain%?packet",
                \GameQ\Protocol::PACKET_RULES => "rules:\x25\xFF",
            ],
            $protocol->getPacket(),
        );

        $protocol->applyChallenge('new');

        self::assertSame('rules:new', $protocol->getPacket(\GameQ\Protocol::PACKET_RULES));
    }

    /**
     * Test options methods
     */
    public function testOptions(): void
    {
        // Check the options getter
        self::assertEquals($this->options, $this->stub->options());

        // Set new options and then check
        $new_options = [
            'key2' => 'value2',
        ];

        $this->stub->options($new_options);

        self::assertEquals($new_options, $this->stub->options());
    }
}

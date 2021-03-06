<?php

/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 *
*/

namespace Test\SystemTag;

use OC\SystemTag\SystemTagManager;
use OC\SystemTag\SystemTagObjectMapper;
use \OCP\SystemTag\ISystemTag;
use \OCP\SystemTag\ISystemTagManager;
use \OCP\SystemTag\ISystemTagObjectMapper;
use \OCP\SystemTag\TagNotFoundException;
use \OCP\IDBConnection;
use \OC\SystemTag\SystemTag;
use Test\TestCase;

/**
 * Class TestSystemTagObjectMapper
 *
 * @group DB
 * @package Test\SystemTag
 */
class SystemTagObjectMapperTest extends TestCase {

	/**
	 * @var ISystemTagManager
	 **/
	private $tagManager;

	/**
	 * @var ISystemTagObjectMapper
	 **/
	private $tagMapper;

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var ISystemTag
	 */
	private $tag1;

	/**
	 * @var ISystemTag
	 */
	private $tag2;

	/**
	 * @var ISystemTag
	 */
	private $tag3;

	public function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->pruneTagsTables();

		$this->tagManager = $this->getMockBuilder('OCP\SystemTag\ISystemTagManager')
			->getMock();

		$this->tagMapper = new SystemTagObjectMapper($this->connection, $this->tagManager);

		$this->tag1 = new SystemTag(1, 'testtag1', false, false);
		$this->tag2 = new SystemTag(2, 'testtag2', true, false);
		$this->tag3 = new SystemTag(3, 'testtag3', false, false);

		$this->tagManager->expects($this->any())
			->method('getTagsByIds')
			->will($this->returnCallback(function($tagIds) {
				$result = [];
				if (in_array(1, $tagIds)) {
					$result[1] = $this->tag1;
				}
				if (in_array(2, $tagIds)) {
					$result[2] = $this->tag2;
				}
				if (in_array(3, $tagIds)) {
					$result[3] = $this->tag3;
				}
				return $result;
			}));

		$this->tagMapper->assignTags(1, 'testtype', $this->tag1->getId());
		$this->tagMapper->assignTags(1, 'testtype', $this->tag2->getId());
		$this->tagMapper->assignTags(2, 'testtype', $this->tag1->getId());
		$this->tagMapper->assignTags(3, 'anothertype', $this->tag1->getId());
	}

	public function tearDown() {
		$this->pruneTagsTables();
		parent::tearDown();
	}

	protected function pruneTagsTables() {
		$query = $this->connection->getQueryBuilder();
		$query->delete(SystemTagObjectMapper::RELATION_TABLE)->execute();
		$query->delete(SystemTagManager::TAG_TABLE)->execute();
	}

	public function testGetTagsForObjects() {
		$tagIdMapping = $this->tagMapper->getTagIdsForObjects(
			[1, 2, 3, 4],
			'testtype'
		);

		$this->assertEquals([
			1 => [$this->tag1->getId(), $this->tag2->getId()],
			2 => [$this->tag1->getId()],
			3 => [],
			4 => [],
		], $tagIdMapping);
	}

	public function testGetObjectsForTags() {
		$objectIds = $this->tagMapper->getObjectIdsForTags(
			[$this->tag1->getId(), $this->tag2->getId(), $this->tag3->getId()],
			'testtype'
		);

		$this->assertEquals([
			1,
			2,
		], $objectIds);
	}

	/**
	 * @expectedException \OCP\SystemTag\TagNotFoundException
	 */
	public function testGetObjectsForNonExistingTag() {
		$this->tagMapper->getObjectIdsForTags(
			[100],
			'testtype'
		);
	}

	public function testAssignUnassignTags() {
		$this->tagMapper->unassignTags(1, 'testtype', [$this->tag1->getId()]);

		$tagIdMapping = $this->tagMapper->getTagIdsForObjects(1, 'testtype');
		$this->assertEquals([
			1 => [$this->tag2->getId()],
		], $tagIdMapping);

		$this->tagMapper->assignTags(1, 'testtype', [$this->tag1->getId()]);
		$this->tagMapper->assignTags(1, 'testtype', $this->tag3->getId());

		$tagIdMapping = $this->tagMapper->getTagIdsForObjects(1, 'testtype');

		$this->assertEquals([
			1 => [$this->tag1->getId(), $this->tag2->getId(), $this->tag3->getId()],
		], $tagIdMapping);
	}

	public function testReAssignUnassignTags() {
		// reassign tag1
		$this->tagMapper->assignTags(1, 'testtype', [$this->tag1->getId()]);

		// tag 3 was never assigned
		$this->tagMapper->unassignTags(1, 'testtype', [$this->tag3->getId()]);

		$this->assertTrue(true, 'No error when reassigning/unassigning');
	}

	/**
	 * @expectedException \OCP\SystemTag\TagNotFoundException
	 */
	public function testAssignNonExistingTags() {
		$this->tagMapper->assignTags(1, 'testtype', [100]);
	}

	public function testAssignNonExistingTagInArray() {
		$caught = false;
		try {
			$this->tagMapper->assignTags(1, 'testtype', [100, $this->tag3->getId()]);
		} catch (TagNotFoundException $e) {
			$caught = true;
		}

		$this->assertTrue($caught, 'Exception thrown');

		$tagIdMapping = $this->tagMapper->getTagIdsForObjects(
			[1],
			'testtype'
		);

		$this->assertEquals([
			1 => [$this->tag1->getId(), $this->tag2->getId()],
		], $tagIdMapping, 'None of the tags got assigned');
	}

	/**
	 * @expectedException \OCP\SystemTag\TagNotFoundException
	 */
	public function testUnassignNonExistingTags() {
		$this->tagMapper->unassignTags(1, 'testtype', [100]);
	}

	public function testUnassignNonExistingTagsInArray() {
		$caught = false;
		try {
			$this->tagMapper->unassignTags(1, 'testtype', [100, $this->tag1->getId()]);
		} catch (TagNotFoundException $e) {
			$caught = true;
		}

		$this->assertTrue($caught, 'Exception thrown');

		$tagIdMapping = $this->tagMapper->getTagIdsForObjects(
			[1],
			'testtype'
		);

		$this->assertEquals([
			1 => [$this->tag1->getId(), $this->tag2->getId()],
		], $tagIdMapping, 'None of the tags got unassigned');
	}

	public function testHaveTagAllMatches() {
		$this->assertTrue(
			$this->tagMapper->haveTag(
				[1],
				'testtype',
				$this->tag1->getId(),
				true
			),
			'object 1 has the tag tag1'
		);

		$this->assertTrue(
			$this->tagMapper->haveTag(
				[1, 2],
				'testtype',
				$this->tag1->getId(),
				true
			),
			'object 1 and object 2 ALL have the tag tag1'
		);

		$this->assertFalse(
			$this->tagMapper->haveTag(
				[1, 2],
				'testtype',
				$this->tag2->getId(),
				true
			),
			'object 1 has tag2 but not object 2, so not ALL of them'
		);

		$this->assertFalse(
			$this->tagMapper->haveTag(
				[2],
				'testtype',
				$this->tag2->getId(),
				true
			),
			'object 2 does not have tag2'
		);

		$this->assertFalse(
			$this->tagMapper->haveTag(
				[3],
				'testtype',
				$this->tag2->getId(),
				true
			),
			'object 3 does not have tag1 due to different type'
		);
	}

	public function testHaveTagAtLeastOneMatch() {
		$this->assertTrue(
			$this->tagMapper->haveTag(
				[1],
				'testtype',
				$this->tag1->getId(),
				false
			),
			'object1 has the tag tag1'
		);

		$this->assertTrue(
			$this->tagMapper->haveTag(
				[1, 2],
				'testtype',
				$this->tag1->getId(),
				false
			),
			'object 1  and object 2 both the tag tag1'
		);

		$this->assertTrue(
			$this->tagMapper->haveTag(
				[1, 2],
				'testtype',
				$this->tag2->getId(),
				false
			),
			'at least object 1 has the tag tag2'
		);

		$this->assertFalse(
			$this->tagMapper->haveTag(
				[2],
				'testtype',
				$this->tag2->getId(),
				false
			),
			'object 2 does not have tag2'
		);

		$this->assertFalse(
			$this->tagMapper->haveTag(
				[3],
				'testtype',
				$this->tag2->getId(),
				false
			),
			'object 3 does not have tag1 due to different type'
		);
	}

	/**
	 * @expectedException \OCP\SystemTag\TagNotFoundException
	 */
	public function testHaveTagNonExisting() {
		$this->tagMapper->haveTag(
			[1],
			'testtype',
			100
		);
	}
}

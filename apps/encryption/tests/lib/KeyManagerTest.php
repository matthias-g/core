<?php
/**
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Clark Tomlinson <fallen013@gmail.com>
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Encryption\Tests;


use OCA\Encryption\KeyManager;
use OCA\Encryption\Session;
use Test\TestCase;

class KeyManagerTest extends TestCase {
	/**
	 * @var KeyManager
	 */
	private $instance;
	/**
	 * @var string
	 */
	private $userId;

	/** @var string */
	private $systemKeyId;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $keyStorageMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $cryptMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $userMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $sessionMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $logMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $utilMock;

	/** @var \PHPUnit_Framework_MockObject_MockObject */
	private $configMock;

	public function setUp() {
		parent::setUp();
		$this->userId = 'user1';
		$this->systemKeyId = 'systemKeyId';
		$this->keyStorageMock = $this->getMock('OCP\Encryption\Keys\IStorage');
		$this->cryptMock = $this->getMockBuilder('OCA\Encryption\Crypto\Crypt')
			->disableOriginalConstructor()
			->getMock();
		$this->configMock = $this->getMock('OCP\IConfig');
		$this->configMock->expects($this->any())
			->method('getAppValue')
			->willReturn($this->systemKeyId);
		$this->userMock = $this->getMock('OCP\IUserSession');
		$this->sessionMock = $this->getMockBuilder('OCA\Encryption\Session')
			->disableOriginalConstructor()
			->getMock();
		$this->logMock = $this->getMock('OCP\ILogger');
		$this->utilMock = $this->getMockBuilder('OCA\Encryption\Util')
			->disableOriginalConstructor()
			->getMock();

		$this->instance = new KeyManager(
			$this->keyStorageMock,
			$this->cryptMock,
			$this->configMock,
			$this->userMock,
			$this->sessionMock,
			$this->logMock,
			$this->utilMock);
	}

	public function testDeleteShareKey() {
		$this->keyStorageMock->expects($this->any())
			->method('deleteFileKey')
			->with($this->equalTo('/path'), $this->equalTo('keyId.shareKey'))
			->willReturn(true);

		$this->assertTrue(
			$this->instance->deleteShareKey('/path', 'keyId')
		);
	}

	public function testGetPrivateKey() {
		$this->keyStorageMock->expects($this->any())
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->equalTo('privateKey'))
			->willReturn('privateKey');


		$this->assertSame('privateKey',
			$this->instance->getPrivateKey($this->userId)
		);
	}

	public function testGetPublicKey() {
		$this->keyStorageMock->expects($this->any())
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->equalTo('publicKey'))
			->willReturn('publicKey');


		$this->assertSame('publicKey',
			$this->instance->getPublicKey($this->userId)
		);
	}

	public function testRecoveryKeyExists() {
		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->with($this->equalTo($this->systemKeyId . '.publicKey'))
			->willReturn('recoveryKey');


		$this->assertTrue($this->instance->recoveryKeyExists());
	}

	public function testCheckRecoveryKeyPassword() {
		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->with($this->equalTo($this->systemKeyId . '.privateKey'))
			->willReturn('recoveryKey');
		$this->cryptMock->expects($this->any())
			->method('decryptPrivateKey')
			->with($this->equalTo('recoveryKey'), $this->equalTo('pass'))
			->willReturn('decryptedRecoveryKey');

		$this->assertTrue($this->instance->checkRecoveryPassword('pass'));
	}

	public function testSetPublicKey() {
		$this->keyStorageMock->expects($this->any())
			->method('setUserKey')
			->with(
				$this->equalTo($this->userId),
				$this->equalTo('publicKey'),
				$this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setPublicKey($this->userId, 'key')
		);
	}

	public function testSetPrivateKey() {
		$this->keyStorageMock->expects($this->any())
			->method('setUserKey')
			->with(
				$this->equalTo($this->userId),
				$this->equalTo('privateKey'),
				$this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setPrivateKey($this->userId, 'key')
		);
	}

	/**
	 * @dataProvider dataTestUserHasKeys
	 */
	public function testUserHasKeys($key, $expected) {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->with($this->equalTo($this->userId), $this->anything())
			->willReturn($key);


		$this->assertSame($expected,
			$this->instance->userHasKeys($this->userId)
		);
	}

	public function dataTestUserHasKeys() {
		return [
			['key', true],
			['', false]
		];
	}

	/**
	 * @expectedException \OCA\Encryption\Exceptions\PrivateKeyMissingException
	 */
	public function testUserHasKeysMissingPrivateKey() {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->willReturnCallback(function ($uid, $keyID, $encryptionModuleId) {
				if ($keyID=== 'privateKey') {
					return '';
				}
				return 'key';
			});

		$this->instance->userHasKeys($this->userId);
	}

	/**
	 * @expectedException \OCA\Encryption\Exceptions\PublicKeyMissingException
	 */
	public function testUserHasKeysMissingPublicKey() {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('getUserKey')
			->willReturnCallback(function ($uid, $keyID, $encryptionModuleId){
				if ($keyID === 'publicKey') {
					return '';
				}
				return 'key';
			});

		$this->instance->userHasKeys($this->userId);

	}

	/**
	 * @dataProvider dataTestInit
	 *
	 * @param bool $useMasterKey
	 */
	public function testInit($useMasterKey) {

		$instance = $this->getMockBuilder('OCA\Encryption\KeyManager')
			->setConstructorArgs(
				[
					$this->keyStorageMock,
					$this->cryptMock,
					$this->configMock,
					$this->userMock,
					$this->sessionMock,
					$this->logMock,
					$this->utilMock
				]
			)->setMethods(['getMasterKeyId', 'getMasterKeyPassword', 'getSystemPrivateKey', 'getPrivateKey'])
			->getMock();

		$this->utilMock->expects($this->once())->method('isMasterKeyEnabled')
			->willReturn($useMasterKey);

		$this->sessionMock->expects($this->at(0))->method('setStatus')
			->with(Session::INIT_EXECUTED);

		$instance->expects($this->any())->method('getMasterKeyId')->willReturn('masterKeyId');
		$instance->expects($this->any())->method('getMasterKeyPassword')->willReturn('masterKeyPassword');
		$instance->expects($this->any())->method('getSystemPrivateKey')->with('masterKeyId')->willReturn('privateMasterKey');
		$instance->expects($this->any())->method('getPrivateKey')->with($this->userId)->willReturn('privateUserKey');

		if($useMasterKey) {
			$this->cryptMock->expects($this->once())->method('decryptPrivateKey')
				->with('privateMasterKey', 'masterKeyPassword', 'masterKeyId')
				->willReturn('key');
		} else {
			$this->cryptMock->expects($this->once())->method('decryptPrivateKey')
				->with('privateUserKey', 'pass', $this->userId)
				->willReturn('key');
		}

		$this->sessionMock->expects($this->once())->method('setPrivateKey')
			->with('key');

		$this->assertTrue($instance->init($this->userId, 'pass'));
	}

	public function dataTestInit() {
		return [
			[true],
			[false]
		];
	}


	public function testSetRecoveryKey() {
		$this->keyStorageMock->expects($this->exactly(2))
			->method('setSystemUserKey')
			->willReturn(true);
		$this->cryptMock->expects($this->any())
			->method('encryptPrivateKey')
			->with($this->equalTo('privateKey'), $this->equalTo('pass'))
			->willReturn('decryptedPrivateKey');


		$this->assertTrue(
			$this->instance->setRecoveryKey('pass',
				array('publicKey' => 'publicKey', 'privateKey' => 'privateKey'))
		);
	}

	public function testSetSystemPrivateKey() {
		$this->keyStorageMock->expects($this->exactly(1))
			->method('setSystemUserKey')
			->with($this->equalTo('keyId.privateKey'), $this->equalTo('key'))
			->willReturn(true);


		$this->assertTrue(
			$this->instance->setSystemPrivateKey('keyId', 'key')
		);
	}

	public function testGetSystemPrivateKey() {
		$this->keyStorageMock->expects($this->exactly(1))
			->method('getSystemUserKey')
			->with($this->equalTo('keyId.privateKey'))
			->willReturn('systemPrivateKey');


		$this->assertSame('systemPrivateKey',
			$this->instance->getSystemPrivateKey('keyId')
		);
	}

	public function testGetEncryptedFileKey() {
		$this->keyStorageMock->expects($this->once())
			->method('getFileKey')
			->with('/', 'fileKey')
			->willReturn(true);

		$this->assertTrue($this->instance->getEncryptedFileKey('/'));
	}

	/**
	 * @dataProvider dataTestGetFileKey
	 *
	 * @param $uid
	 * @param $isMasterKeyEnabled
	 * @param $privateKey
	 * @param $expected
	 */
	public function testGetFileKey($uid, $isMasterKeyEnabled, $privateKey, $expected) {

		$path = '/foo.txt';

		if ($isMasterKeyEnabled) {
			$expectedUid = 'masterKeyId';
		} else {
			$expectedUid = $uid;
		}

		$this->invokePrivate($this->instance, 'masterKeyId', ['masterKeyId']);

		$this->keyStorageMock->expects($this->at(0))
			->method('getFileKey')
			->with($path, 'fileKey', 'OC_DEFAULT_MODULE')
			->willReturn(true);

		$this->keyStorageMock->expects($this->at(1))
			->method('getFileKey')
			->with($path, $expectedUid . '.shareKey', 'OC_DEFAULT_MODULE')
			->willReturn(true);

		if (is_null($uid)) {
			$this->keyStorageMock->expects($this->once())
				->method('getSystemUserKey')
				->willReturn(true);
			$this->cryptMock->expects($this->once())
				->method('decryptPrivateKey')
				->willReturn($privateKey);
		} else {
			$this->keyStorageMock->expects($this->never())
				->method('getSystemUserKey');
			$this->utilMock->expects($this->once())->method('isMasterKeyEnabled')
				->willReturn($isMasterKeyEnabled);
			$this->sessionMock->expects($this->once())->method('getPrivateKey')->willReturn($privateKey);
		}

		if($privateKey) {
			$this->cryptMock->expects($this->once())
				->method('multiKeyDecrypt')
				->willReturn(true);
		} else {
			$this->cryptMock->expects($this->never())
				->method('multiKeyDecrypt');
		}

		$this->assertSame($expected,
			$this->instance->getFileKey($path, $uid)
		);

	}

	public function dataTestGetFileKey() {
		return [
			['user1', false, 'privateKey', true],
			['user1', false, false, ''],
			['user1', true, 'privateKey', true],
			['user1', true, false, ''],
			['', false, 'privateKey', true],
			['', false, false, ''],
			['', true, 'privateKey', true],
			['', true, false, '']
		];
	}

	public function testDeletePrivateKey() {
		$this->keyStorageMock->expects($this->once())
			->method('deleteUserKey')
			->with('user1', 'privateKey')
			->willReturn(true);

		$this->assertTrue(self::invokePrivate($this->instance,
			'deletePrivateKey',
			[$this->userId]));
	}

	public function testDeleteAllFileKeys() {
		$this->keyStorageMock->expects($this->once())
			->method('deleteAllFileKeys')
			->willReturn(true);

		$this->assertTrue($this->instance->deleteAllFileKeys('/'));
	}

	/**
	 * test add public share key and or recovery key to the list of public keys
	 *
	 * @dataProvider dataTestAddSystemKeys
	 *
	 * @param array $accessList
	 * @param array $publicKeys
	 * @param string $uid
	 * @param array $expectedKeys
	 */
	public function testAddSystemKeys($accessList, $publicKeys, $uid, $expectedKeys) {

		$publicShareKeyId = 'publicShareKey';
		$recoveryKeyId = 'recoveryKey';

		$this->keyStorageMock->expects($this->any())
			->method('getSystemUserKey')
			->willReturnCallback(function($keyId, $encryptionModuleId) {
				return $keyId;
			});

		$this->utilMock->expects($this->any())
			->method('isRecoveryEnabledForUser')
			->willReturnCallback(function($uid) {
				if ($uid === 'user1') {
					return true;
				}
				return false;
			});

		// set key IDs
		self::invokePrivate($this->instance, 'publicShareKeyId', [$publicShareKeyId]);
		self::invokePrivate($this->instance, 'recoveryKeyId', [$recoveryKeyId]);

		$result = $this->instance->addSystemKeys($accessList, $publicKeys, $uid);

		foreach ($expectedKeys as $expected) {
			$this->assertArrayHasKey($expected, $result);
		}

		$this->assertSameSize($expectedKeys, $result);
	}

	/**
	 * data provider for testAddSystemKeys()
	 *
	 * @return array
	 */
	public function dataTestAddSystemKeys() {
		return array(
			array(['public' => true],[], 'user1', ['publicShareKey', 'recoveryKey']),
			array(['public' => false], [], 'user1', ['recoveryKey']),
			array(['public' => true],[], 'user2', ['publicShareKey']),
			array(['public' => false], [], 'user2', []),
		);
	}

	public function testGetMasterKeyId() {
		$this->assertSame('systemKeyId', $this->instance->getMasterKeyId());
	}

	public function testGetPublicMasterKey() {
		$this->keyStorageMock->expects($this->once())->method('getSystemUserKey')
			->with('systemKeyId.publicKey', \OCA\Encryption\Crypto\Encryption::ID)
			->willReturn(true);

		$this->assertTrue(
			$this->instance->getPublicMasterKey()
		);
	}

	public function testGetMasterKeyPassword() {
		$this->configMock->expects($this->once())->method('getSystemValue')->with('secret')
			->willReturn('password');

		$this->assertSame('password',
			$this->invokePrivate($this->instance, 'getMasterKeyPassword', [])
		);
	}

	/**
	 * @expectedException \Exception
	 */
	public function testGetMasterKeyPasswordException() {
		$this->configMock->expects($this->once())->method('getSystemValue')->with('secret')
			->willReturn('');

		$this->invokePrivate($this->instance, 'getMasterKeyPassword', []);
	}

	/**
	 * @dataProvider dataTestValidateMasterKey
	 *
	 * @param $masterKey
	 */
	public function testValidateMasterKey($masterKey) {

		/** @var \OCA\Encryption\KeyManager | \PHPUnit_Framework_MockObject_MockObject $instance */
		$instance = $this->getMockBuilder('OCA\Encryption\KeyManager')
			->setConstructorArgs(
				[
					$this->keyStorageMock,
					$this->cryptMock,
					$this->configMock,
					$this->userMock,
					$this->sessionMock,
					$this->logMock,
					$this->utilMock
				]
			)->setMethods(['getPublicMasterKey', 'setSystemPrivateKey', 'getMasterKeyPassword'])
			->getMock();

		$instance->expects($this->once())->method('getPublicMasterKey')
			->willReturn($masterKey);

		$instance->expects($this->any())->method('getMasterKeyPassword')->willReturn('masterKeyPassword');
		$this->cryptMock->expects($this->any())->method('generateHeader')->willReturn('header');

		if(empty($masterKey)) {
			$this->cryptMock->expects($this->once())->method('createKeyPair')
				->willReturn(['publicKey' => 'public', 'privateKey' => 'private']);
			$this->keyStorageMock->expects($this->once())->method('setSystemUserKey')
				->with('systemKeyId.publicKey', 'public', \OCA\Encryption\Crypto\Encryption::ID);
			$this->cryptMock->expects($this->once())->method('encryptPrivateKey')
				->with('private', 'masterKeyPassword', 'systemKeyId')
				->willReturn('EncryptedKey');
			$instance->expects($this->once())->method('setSystemPrivateKey')
				->with('systemKeyId', 'headerEncryptedKey');
		} else {
			$this->cryptMock->expects($this->never())->method('createKeyPair');
			$this->keyStorageMock->expects($this->never())->method('setSystemUserKey');
			$this->cryptMock->expects($this->never())->method('encryptPrivateKey');
			$instance->expects($this->never())->method('setSystemPrivateKey');
		}

		$instance->validateMasterKey();
	}

	public function dataTestValidateMasterKey() {
		return [
			['masterKey'],
			['']
		];
	}

}

<?php
/**
 * ownCloud - Addressbook
 *
 * @author Jakob Sack
 * @author Thomas Tanghus
 * @copyright 2011 Jakob Sack mail@jakobsack.de
 * @copyright 2011-2014 Thomas Tanghus (thomas@tanghus.net)
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Backends
use OCA\DAV\CardDAV\AddressBookRoot;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\Connector\Sabre\AppEnabledPlugin;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin;
use OCA\DAV\Connector\Sabre\MaintenancePlugin;
use OCA\DAV\Connector\Sabre\Principal;
use Sabre\CardDAV\Plugin;

$authBackend = new Auth(
	\OC::$server->getSession(),
	\OC::$server->getUserSession(),
	'principals/'
);
$principalBackend = new Principal(
	\OC::$server->getUserManager(),
	\OC::$server->getGroupManager(),
	'principals/'
);
$db = \OC::$server->getDatabaseConnection();
$carddavBackend = new CardDavBackend($db, $principalBackend);

// Root nodes
$principalCollection = new \Sabre\CalDAV\Principal\Collection($principalBackend);
$principalCollection->disableListing = true; // Disable listing

$addressBookRoot = new AddressBookRoot($principalBackend, $carddavBackend);
$addressBookRoot->disableListing = true; // Disable listing

$nodes = array(
	$principalCollection,
	$addressBookRoot,
);

// Fire up server
$server = new \Sabre\DAV\Server($nodes);
$server->httpRequest->setUrl(\OC::$server->getRequest()->getRequestUri());
$server->setBaseUri($baseuri);
// Add plugins
$server->addPlugin(new MaintenancePlugin());
$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, 'ownCloud'));
$server->addPlugin(new Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());
$server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
$server->addPlugin(new ExceptionLoggerPlugin('carddav', \OC::$server->getLogger()));

// And off we go!
$server->exec();

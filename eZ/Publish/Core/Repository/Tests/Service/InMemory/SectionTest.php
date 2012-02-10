<?php
/**
 * File contains: eZ\Publish\Core\Repository\Tests\Service\Legacy\SectionTest class
 *
 * @copyright Copyright (C) 1999-2011 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Repository\Tests\Service\InMemory;
use eZ\Publish\Core\Repository\Tests\Service\SectionBase as BaseSectionServiceTest,
    eZ\Publish\Core\Repository\Repository,
    eZ\Publish\Core\IO\InMemoryHandler as InMemoryIoHandler,
    eZ\Publish\Core\Persistence\InMemory\Handler as InMemoryPersistenceHandler;

/**
 * Test case for Section Service using Legacy storage class
 *
 */
class SectionTest extends BaseSectionServiceTest
{
    protected function getRepository()
    {
        return new Repository( new InMemoryPersistenceHandler(), new InMemoryIoHandler() );
    }
}
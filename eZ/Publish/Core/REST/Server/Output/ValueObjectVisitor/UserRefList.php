<?php
/**
 * File containing the UserRefList ValueObjectVisitor class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Server\Output\ValueObjectVisitor;

use eZ\Publish\Core\REST\Common\Output\ValueObjectVisitor;
use eZ\Publish\Core\REST\Common\Output\Generator;
use eZ\Publish\Core\REST\Common\Output\Visitor;

/**
 * UserRefList value object visitor
 */
class UserRefList extends ValueObjectVisitor
{
    /**
     * Visit struct returned by controllers
     *
     * @param \eZ\Publish\Core\REST\Common\Output\Visitor $visitor
     * @param \eZ\Publish\Core\REST\Common\Output\Generator $generator
     * @param mixed $data
     */
    public function visit( Visitor $visitor, Generator $generator, $data )
    {
        $generator->startObjectElement( 'UserRefList' );
        $visitor->setHeader( 'Content-Type', $generator->getMediaType( 'UserRefList' ) );

        $generator->startAttribute( 'href', $data->path );
        $generator->endAttribute( 'href' );

        $generator->startList( 'User' );
        foreach ( $data->users as $user )
        {
            $generator->startObjectElement( 'User' );

            $generator->startAttribute( 'href', $this->urlHandler->generate( 'user', array( 'user' => $user->id ) ) );
            $generator->endAttribute( 'href' );

            $generator->endObjectElement( 'User' );
        }
        $generator->endList( 'User' );

        $generator->endObjectElement( 'UserRefList' );
    }
}

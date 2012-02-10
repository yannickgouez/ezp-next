<?php
/**
 * File contains: eZ\Publish\Core\Persistence\Legacy\Tests\Content\Language\MaskGeneratorTest class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Legacy\Tests\Content\Language;
use eZ\Publish\Core\Persistence\Legacy\Tests\Content\LanguageAwareTestCase,
    eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator,
    eZ\Publish\Core\Persistence\Legacy\Content\Language\Cache,
    eZ\Publish\SPI\Persistence\Content\Language,
    ezp\Base\Exception;

/**
 * Test case for Language MaskGenerator
 */
class MaskGeneratorTest extends LanguageAwareTestCase
{

    /**
     * @param array $languages
     * @param int $expectedMask
     * @return void
     * @covers eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator::generateLanguageMask
     * @dataProvider getLanguageMaskData
     */
    public function testGenerateLanguageMask( array $languages, $expectedMask )
    {
        $generator = $this->getMaskGenerator();

        $this->assertSame(
            $expectedMask,
            $generator->generateLanguageMask( $languages )
        );
    }

    /**
     * Returns test data for {@link testGenerateLanguageMask()}
     *
     * @return array
     */
    public static function getLanguageMaskData()
    {
        return array(
            'error' => array(
                array(),
                0,
            ),
            'single_lang' => array(
                array( 'eng-GB' => true ),
                4,
            ),
            'multi_lang' => array(
                array( 'eng-US' => true, 'eng-GB' => true ),
                6,
            ),
            'always_available' => array(
                array( 'always-available' => 'eng-US', 'eng-US' => true ),
                3,
            ),
            'full' => array(
                array( 'always-available' => 'eng-US', 'eng-US' => true, 'eng-GB' => true ),
                7,
            ),
        );
    }

    /**
     * @param string $languageCode
     * @param boolean $alwaysAvailable
     * @param int $expectedIndicator
     * @return void
     * @covers eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator::generateLanguageIndicator
     * @dataProvider getLanguageIndicatorData
     */
    public function testGenerateLanguageIndicator(
        $languageCode, $alwaysAvailable, $expectedIndicator )
    {
        $generator = $this->getMaskGenerator();

        $this->assertSame(
            $expectedIndicator,
            $generator->generateLanguageIndicator( $languageCode, $alwaysAvailable )
        );
    }

    /**
     * Returns test data for {@link testGenerateLanguageIndicator()}
     *
     * @return array
     */
    public static function getLanguageIndicatorData()
    {
        return array(
            'not_available' => array(
                'eng-GB',
                false,
                4,
            ),
            'always_available' => array(
                'eng-US',
                true,
                3,
            ),
        );
    }

    /**
     * @return void
     * @covers eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator::isLanguageAlwaysAvailable
     */
    public function testIsLanguageAlwaysAvailable()
    {
        $generator = $this->getMaskGenerator();

        $this->assertTrue(
            $generator->isLanguageAlwaysAvailable(
                'eng-GB',
                array(
                    'always-available' => 'eng-GB',
                    'eng-GB' => 'lala'
                )
            )
        );
    }

    /**
     * @return void
     * @covers eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator::isLanguageAlwaysAvailable
     */
    public function testIsLanguageAlwaysAvailableOtherLanguage()
    {
        $generator = $this->getMaskGenerator();

        $this->assertFalse(
            $generator->isLanguageAlwaysAvailable(
                'eng-GB',
                array(
                    'always-available' => 'eng-US',
                    'eng-GB' => 'lala'
                )
            )
        );
    }

    /**
     * @return void
     * @covers eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator::isLanguageAlwaysAvailable
     */
    public function testIsLanguageAlwaysAvailableNoDefault()
    {
        $generator = $this->getMaskGenerator();

        $this->assertFalse(
            $generator->isLanguageAlwaysAvailable(
                'eng-GB',
                array(
                    'eng-GB' => 'lala'
                )
            )
        );
    }

    /**
     * Returns the mask generator to test
     *
     * @return \eZ\Publish\Core\Persistence\Legacy\Content\Language\MaskGenerator
     */
    protected function getMaskGenerator()
    {
        return new MaskGenerator( $this->getLanguageLookupMock() );
    }

    /**
     * Returns the test suite with all tests declared in this class.
     *
     * @return \PHPUnit_Framework_TestSuite
     */
    public static function suite()
    {
        return new \PHPUnit_Framework_TestSuite( __CLASS__ );
    }
}
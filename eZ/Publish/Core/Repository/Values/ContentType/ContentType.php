<?php
namespace eZ\Publish\Core\Repository\Values\ContentType;

use eZ\Publish\API\Repository\Values\ContentType\ContentType as APIContentType,
    eZ\Publish\API\Repository\Values\ContentType\FieldDefinition,
    eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup;

/**
 * this class represents a content type value
 *
 * @property-read array $names calls getNames() or on access getName($language)
 * @property-read array $descriptions calls getDescriptions() or on access getDescription($language)
 * @property-read array $contentTypeGroups calls getContentTypeGroups
 * @property-read array $fieldDefinitions calls getFieldDefinitions() or on access getFieldDefinition($fieldDefIdentifier)
 * @property-read int $id the id of the content type
 * @property-read int $status the status of the content type. One of ContentType::STATUS_DEFINED|ContentType::STATUS_DRAFT|ContentType::STATUS_MODIFIED
 * @property-read string $identifier the identifier of the content type
 * @property-read \DateTime $createdDate the date of the creation of this content type
 * @property-read \DateTime $modificationDate the date of the last modification of this content type
 * @property-read int $creatorId the user id of the creator of this content type
 * @property-read int $modifierId the user id of the user which has last modified this content type
 * @property-read string $remoteId a global unique id of the content object
 * @property-read string $urlAliasSchema URL alias schema. If nothing is provided, $nameSchema will be used instead.
 * @property-read string $nameSchema  The name schema.
 * @property-read boolean $isContainer Determines if the type is allowd to have children
 * @property-read string $mainLanguageCode the main language of the content type names and description used for fallback.
 * @property-read boolean $defaultAlwaysAvailable if an instance of acontent type is created the always available flag is set by default this this value.
 * @property-read int $defaultSortField Specifies which property the child locations should be sorted on by default when created. Valid values are found at {@link Location::SORT_FIELD_*}
 * @property-read int $defaultSortOrder Specifies whether the sort order should be ascending or descending by default when created. Valid values are {@link Location::SORT_ORDER_*}
 */
class ContentType extends APIContentType
{
    /**
     * Holds the collection of names with languageCode keys
     *
     * @var array
     */
    protected $names = array();

    /**
     * Holds the collection of descriptions with languageCode keys
     *
     * @var array
     */
    protected $descriptions = array();

    /**
     * Holds the collection of contenttypegroups the contenttype is assigned to
     *
     * @var array
     */
    protected $contentTypeGroups = array();

    /**
     * Holds the collection of field definitions for the contenttype
     *
     * @var array
     */
    protected $fieldDefinitions = array();

    /**
     * This method returns the human readable name in all provided languages
     * of the content type
     *
     * The structure of the return value is:
     * <code>
     * array( 'eng' => '<name_eng>', 'de' => '<name_de>' );
     * </code>
     *
     * @return string[]
     */
    public function getNames()
    {
        return $this->names;
    }

    /**
     * this method returns the name of the content type in the given language
     *
     * @param string $languageCode
     * @return string the name for the given language or null if none existis.
     */
    public function getName( $languageCode )
    {
        if ( array_key_exists( $languageCode, $this->names ) )
        {
            return $this->names[$languageCode];
        }

        return null;
    }

    /**
     *  This method returns the human readable description of the content type
     *
     * The structure of this field is:
     * <code>
     * array( 'eng' => '<description_eng>', 'de' => '<description_de>' );
     * </code>
     *
     * @return string[]
     */
    public function getDescriptions()
    {
        return $this->descriptions;
    }

    /**
     * this method returns the name of the content type in the given language
     *
     * @param string $languageCode
     * @return string the description for the given language or null if none existis.
     */
    public function getDescription( $languageCode )
    {
        if ( array_key_exists( $languageCode, $this->descriptions ) )
        {
            return $this->descriptions[$languageCode];
        }

        return null;
    }

    /**
     * This method returns the content type groups this content type is assigned to
     *
     * @return array an array of {@link ContentTypeGroup}
     */
    public function getContentTypeGroups()
    {
        return $this->contentTypeGroups;
    }

    /**
     * This method returns the content type field definitions from this type
     *
     * @return array an array of {@link FieldDefinition}
     */
    public function getFieldDefinitions()
    {
        return $this->fieldDefinitions;
    }

    /**
     * this method returns the field definition for the given identifier
     *
     * @param $fieldDefinitionIdentifier
     * @return FieldDefinition
     */
    public function getFieldDefinition( $fieldDefinitionIdentifier )
    {
        if ( array_key_exists( $fieldDefinitionIdentifier, $this->fieldDefinitions ) )
        {
            return $this->fieldDefinitions[$fieldDefinitionIdentifier];
        }

        return null;
    }
}
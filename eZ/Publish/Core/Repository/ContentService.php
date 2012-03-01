<?php
/**
 * @package eZ\Publish\Core\Repository
 */
namespace eZ\Publish\Core\Repository;

use eZ\Publish\API\Repository\ContentService as ContentServiceInterface,
    eZ\Publish\API\Repository\Repository as RepositoryInterface,
    eZ\Publish\SPI\Persistence\Handler;


use eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct as APIContentUpdateStruct;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\TranslationInfo;
use eZ\Publish\API\Repository\Values\Content\TranslationValues as APITranslationValues;
use eZ\Publish\API\Repository\Values\Content\ContentCreateStruct as APIContentCreateStruct;
use eZ\Publish\API\Repository\Values\Content\ContentMetaDataUpdateStruct;
use eZ\Publish\API\Repository\Values\Content\VersionInfo as APIVersionInfo;
use eZ\Publish\API\Repository\Values\Content\ContentInfo as APIContentInfo;
use \eZ\Publish\API\Repository\Values\User\User;
use \eZ\Publish\API\Repository\Values\Content\LocationCreateStruct;

use eZ\Publish\API\Repository\Values\Content\Field;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;

use eZ\Publish\Core\Repository\Values\Content\Content;
use eZ\Publish\Core\Repository\Values\Content\ContentInfo;
use eZ\Publish\Core\Repository\Values\Content\VersionInfo;
use eZ\Publish\Core\Repository\Values\Content\ContentCreateStruct;
use eZ\Publish\Core\Repository\Values\Content\ContentUpdateStruct;
use eZ\Publish\Core\Repository\Values\Content\TranslationValues;

use eZ\Publish\SPI\Persistence\Content\Query\Criterion\ContentId as CriterionContentId;
use eZ\Publish\SPI\Persistence\Content\Query\Criterion\RemoteId as CriterionRemoteId;

use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Base\Exceptions\ContentValidationException;
use eZ\Publish\Core\Base\Exceptions\ContentFieldValidationException;

use eZ\Publish\SPI\Persistence\Content as SPIContent;
use eZ\Publish\SPI\Persistence\Content\CreateStruct as SPIContentCreateStruct;
use eZ\Publish\SPI\Persistence\Content\Field as SPIField;
use eZ\Publish\SPI\Persistence\Content\FieldValue as SPIFieldValue;

/**
 * Legacy exceptions
 * @todo remove when storage exceptions are defined
 */
use eZ\Publish\Core\Persistence\Legacy\Exception\InvalidObjectCount;

/**
* This class provides service methods for managing content
 *
 * @example Examples/content.php
 *
 * @package eZ\Publish\API\Repository
 */
class ContentService implements ContentServiceInterface
{
    /**
     * @var \eZ\Publish\API\Repository\Repository
     */
    protected $repository;

    /**
     * @var \eZ\Publish\SPI\Persistence\Handler
     */
    protected $persistenceHandler;

    /**
     * Setups service with reference to repository object that created it & corresponding handler
     *
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param \eZ\Publish\SPI\Persistence\Handler $handler
     */
    public function __construct( RepositoryInterface $repository, Handler $handler )
    {
        $this->repository = $repository;
        $this->persistenceHandler = $handler;
    }

    /**
     * Loads a content info object.
     *
     * To load fields use loadContent
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to read the content
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the content with the given id does not exist
     *
     * @param int $contentId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\ContentInfo
     */
    public function loadContentInfo( $contentId )
    {
        try
        {
            $spiContent = $this->persistenceHandler->searchHandler()->findSingle(
                new CriterionContentId( $contentId )
            );
        }
            // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $contentId,
                $e
            );
        }

        return $this->buildContentInfoDomainObject( $spiContent );
    }

    /**
     * Loads a content info object for the given remoteId.
     *
     * To load fields use loadContent
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to create the content in the given location
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the content with the given remote id does not exist
     *
     * @param string $remoteId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\ContentInfo
     */
    public function loadContentInfoByRemoteId( $remoteId )
    {
        try
        {
            $spiContent = $this->persistenceHandler->searchHandler()->findSingle(
                new CriterionRemoteId( $remoteId )
            );
        }
        // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $remoteId,
                $e
            );
        }

        return $this->buildContentInfoDomainObject( $spiContent );
    }

    /**
     * Builds a ContentInfo domain object from value object returned from persistence
     *
     * @param \eZ\Publish\SPI\Persistence\Content $spiContent
     *
     * @return \eZ\Publish\Core\Repository\Values\Content\ContentInfo
     */
    protected function buildContentInfoDomainObject( SPIContent $spiContent )
    {
        $modifiedDate = new \DateTime( "@{$spiContent->modified}" );
        $publishedDate = new \DateTime( "@{$spiContent->published}" );
        $language = $this->persistenceHandler->contentLanguageHandler()->load(
            $spiContent->initialLanguageId
        );
        $published = $spiContent->status === SPIContent::STATUS_PUBLISHED;

        // @todo add content name
        return new ContentInfo(
            array(
                "repository"       => $this->repository,
                "contentTypeId"    => $spiContent->typeId,
                "contentId"        => $spiContent->id,
                //"name"             => ,
                "sectionId"        => $spiContent->sectionId,
                "currentVersionNo" => $spiContent->currentVersionNo,
                "published"        => $published,
                "ownerId"          => $spiContent->ownerId,
                "modifiedDate"     => $modifiedDate,
                "publishedDate"    => $publishedDate,
                "alwaysAvailable"  => $spiContent->alwaysAvailable,
                "remoteId"         => $spiContent->remoteId,
                "mainLanguageCode" => $language->languageCode
            )
        );
    }

    /**
     * loads a version info of the given content object.
     *
     * If no version number is given, the method returns the current version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the version with the given number does not exist
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param int $versionNo the version number. If not given the current version is returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\VersionInfo
     */
    public function loadVersionInfo( APIContentInfo $contentInfo, $versionNo = null )
    {
        try
        {
            if ( $versionNo === null )
            {
                $versionNo = $this->persistenceHandler->searchHandler()->findSingle(
                    new CriterionContentId( $contentInfo->contentId )
                )->currentVersionNo;
            }

            $spiContent = $this->persistenceHandler->contentHandler()->load(
                $contentInfo->contentId,
                $versionNo
            );
        }
        // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $contentInfo->contentId,
                $e
            );
        }

        return $this->buildVersionInfoDomainObject( $spiContent );
    }

    /**
     * loads a version info of the given content object id.
     *
     * If no version number is given, the method returns the current version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the version with the given number does not exist
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param int $contentId
     * @param int $versionNo the version number. If not given the current version is returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\VersionInfo
     */
    public function loadVersionInfoById( $contentId, $versionNo = null )
    {
        try
        {
            if ( $versionNo === null )
                $versionNo = $this->persistenceHandler->searchHandler()->findSingle(
                    new CriterionContentId( $contentId )
                )->currentVersionNo;

            $spiContent = $this->persistenceHandler->contentHandler()->load(
                $contentId,
                $versionNo
            );
        }
        // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $contentId,
                $e
            );
        }

        return $this->buildVersionInfoDomainObject( $spiContent );
    }

    /**
     * Builds a VersionInfo domain object from value object returned from persistence
     *
     * @param \eZ\Publish\SPI\Persistence\Content $spiContent
     *
     * @return VersionInfo
     */
    protected function buildVersionInfoDomainObject( SPIContent $spiContent )
    {
        $modifiedDate = new \DateTime( "@{$spiContent->version->modified}" );
        $createdDate = new \DateTime( "@{$spiContent->version->created}" );
        $language = $this->persistenceHandler->contentLanguageHandler()->load(
            $spiContent->version->initialLanguageId
        );
        $languageCodes = array();
        foreach ( $spiContent->version->languageIds as $languageId )
        {
            $languageCodes[] = $this->persistenceHandler->contentLanguageHandler()->load(
                $languageId
            )->languageCode;
        }

        return new VersionInfo(
            array(
                "contentInfo"         => $this->buildContentInfoDomainObject( $spiContent ),
                "id"                  => $spiContent->version->id,
                "versionNo"           => $spiContent->version->versionNo,
                "modifiedDate"        => $modifiedDate,
                "creatorId"           => $spiContent->version->creatorId,
                "createdDate"         => $createdDate,
                "status"              => $spiContent->version->status,
                "initialLanguageCode" => $language->languageCode,
                "languageCodes"       => $languageCodes
            )
        );
    }

    /**
     * loads content in a version for the given content info object.
     *
     * If no version number is given, the method returns the current version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if version with the given number does not exist
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param array $languages A language filter for fields. If not given all languages are returned
     * @param int $versionNo the version number. If not given the current version is returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function loadContentByContentInfo( APIContentInfo $contentInfo, array $languages = null, $versionNo = null )
    {
        if ( $versionNo === null ) $versionNo = $contentInfo->currentVersionNo;

        $spiContent = $this->persistenceHandler->contentHandler()->load(
            $contentInfo->contentId,
            $versionNo,
            $languages
        );

        return $this->buildContentDomainObject( $spiContent );
    }

    /**
     * loads content in the version given by version info.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     * @param array $languages A language filter for fields. If not given all languages are returned
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function loadContentByVersionInfo( APIVersionInfo $versionInfo, array $languages = null )
    {
        $spiContent = $this->persistenceHandler->contentHandler()->load(
            $versionInfo->contentId,
            $versionInfo->versionNo,
            $languages
        );

        return $this->buildContentDomainObject( $spiContent );
    }

    /**
     * loads content in a version of the given content object.
     *
     * If no version number is given, the method returns the current version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the content or version with the given id does not exist
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param int $contentId
     * @param array $languages A language filter for fields. If not given all languages are returned
     * @param int $versionNo the version number. If not given the current version is returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function loadContent( $contentId, array $languages = null, $versionNo = null )
    {
        try
        {
            if ( $versionNo === null )
            {
                $versionNo = $this->persistenceHandler->searchHandler()->findSingle(
                    new CriterionContentId( $contentId )
                )->currentVersionNo;
            }

            $spiContent = $this->persistenceHandler->contentHandler()->load(
                $contentId,
                $versionNo,
                $languages
            );
        }
        // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $contentId,
                $e
            );
        }

        return $this->buildContentDomainObject( $spiContent );
    }

    /**
     * loads content in a version for the content object reference by the given remote id.
     *
     * If no version is given, the method returns the current version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException - if the content or version with the given remote id does not exist
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load this version
     *
     * @param string $remoteId
     * @param array $languages A language filter for fields. If not given all languages are returned
     * @param int $versionNo the version number. If not given the current version is returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function loadContentByRemoteId( $remoteId, array $languages = null, $versionNo = null )
    {
        try
        {
            $spiContent = $this->persistenceHandler->searchHandler()->findSingle(
                new CriterionRemoteId( $remoteId )
            );
        }
        // @TODO: exceptions are not defined in abstract handler, using one from Legacy storage
        catch ( InvalidObjectCount $e )
        {
            throw new NotFoundException(
                "Content",
                $remoteId,
                $e
            );
        }

        $contentId = $spiContent->id;
        if ( $versionNo === null )
        {
            $versionNo = $spiContent->currentVersionNo;
        }

        $spiContent = $this->persistenceHandler->contentHandler()->load(
            $contentId,
            $versionNo,
            $languages
        );

        return $this->buildContentDomainObject( $spiContent );
    }

    /**
     * Builds a Content domain object from value object returned from persistence
     *
     * @param \eZ\Publish\SPI\Persistence\Content $spiContent
     *
     * @return \eZ\Publish\Core\Repository\Values\Content\Content
     */
    protected function buildContentDomainObject( SPIContent $spiContent )
    {
        return new Content(
            array(
                "id"                => $spiContent->id,
                "status"            => $spiContent->status,
                "typeId"            => $spiContent->typeId,
                "sectionId"         => $spiContent->sectionId,
                "ownerId"           => $spiContent->ownerId,
                "modified"          => $spiContent->modified,
                "published"         => $spiContent->published,
                "currentVersionNo"  => $spiContent->currentVersionNo,
                "version"           => $spiContent->version,
                "locations"         => $spiContent->locations,
                "alwaysAvailable"   => $spiContent->alwaysAvailable,
                "remoteId"          => $spiContent->remoteId,
                "initialLanguageId" => $spiContent->initialLanguageId
            )
        );
    }

    /**
     * Creates a new content draft assigned to the authenticated user.
     *
     * If a different userId is given in $contentCreateStruct it is assigned to the given user
     * but this required special rights for the authenticated user
     * (this is useful for content staging where the transfer process does not
     * have to authenticate with the user which created the content object in the source server).
     * The user has to publish the draft if it should be visible.
     * In 4.x at least one location has to be provided in the location creation array.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to create the content in the given location
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if there is a provided remoteId which exists in the system
     *                                                            or (4.x) there is no location provided
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException if a field in the $contentCreateStruct is not valid
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException if a required field is missing
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentCreateStruct $contentCreateStruct
     * @param array $locationCreateStructs an array of {@link \eZ\Publish\API\Repository\Values\Content\LocationCreateStruct} for each location parent under which a location should be created for the content
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content - the newly created content draft
     */
    public function createContent( APIContentCreateStruct $contentCreateStruct, array $locationCreateStructs = array() )
    {
        if ( count( $locationCreateStructs ) === 0 )
        {
            throw new InvalidArgumentException(
                '$locationCreateStructs',
                "array of locations is empty"
            );
        }

        if ( $contentCreateStruct->remoteId !== null )
        {
            try
            {
                $this->persistenceHandler->searchHandler()->findSingle(
                    new CriterionRemoteId(
                        $contentCreateStruct->remoteId
                    )
                );

                throw new InvalidArgumentException(
                    '$contentCreateStruct->remoteId',
                    "content with given remoteId already exists"
                );
            }
            // @todo fix: exception not defined in interface, using the one from Legacy storage
            catch ( InvalidObjectCount $e ) {}

            $remoteId = $contentCreateStruct->remoteId;
        }
        else $remoteId = md5( uniqid( get_class( $contentCreateStruct ), true ) );

        if ( $contentCreateStruct->ownerId === null )
            $contentCreateStruct->ownerId = $this->repository->getCurrentUser()->id;
        else
        {
            // @todo: check for user permissions
        }

        $fields = array();
        $languageCodes = array( $contentCreateStruct->mainLanguageCode );

        foreach ( $contentCreateStruct->fields as $field )
        {
            $fields[$field->fieldDefIdentifier][$field->languageCode] = $field;
            $languageCodes[] = $field->languageCode;
        }

        $languageCodes = array_unique( $languageCodes );

        $spiFields = array();
        $validators = array();
        $areFieldsValid = true;
        foreach ( $contentCreateStruct->contentType->getFieldDefinitions() as $fieldDefinition )
        {
            foreach ( $languageCodes as $languageCode )
            {
                if ( isset( $fields[$fieldDefinition->identifier][$languageCode] ) )
                {
                    $field = $fields[$fieldDefinition->identifier][$languageCode];
                    // @todo using FieldTypeRefactoring branch code
                    $fieldType = FieldTypeFactory::build( $fieldDefinition->identifier );
                    $fieldValue = FieldTypeFactory::buildValue(
                        $fieldDefinition->identifier,
                        $fieldType->acceptValue( $field->value )
                    );

                    // @TODO: this should be checked by $fieldValue->hasContent or something similar, not decided yet
                    if ( $fieldDefinition->isRequired && empty( $fieldValue ) )
                    {
                        throw new ContentValidationException( '@TODO: What error code should be used?' );
                    }

                    if ( !$this->validateField( $fieldDefinition, $fieldType, $fieldValue ) )
                    {
                        $areFieldsValid = false;
                    }

                    if ( !$areFieldsValid ) continue;

                    $spiFields[] = new SPIField(
                        array(
                            "id"                 =>  $field->id,
                            "value"              =>  $this->buildSPIFieldValue( $fieldValue, $fieldDefinition, $fieldType ),
                            "languageCode"       =>  $field->languageCode,
                            "fieldDefIdentifier" =>  $field->fieldDefIdentifier
                        )
                    );
                }
                else
                {
                    $field = $fields[$fieldDefinition->identifier][$languageCode];
                    // @todo using FieldTypeRefactoring branch code
                    $fieldType = FieldTypeFactory::build( $fieldDefinition->identifier );
                    $fieldValue = FieldTypeFactory::buildValue(
                        $fieldDefinition->identifier,
                        $fieldType->acceptValue( $fieldDefinition->defaultValue )
                    );

                    // @TODO: this should be checked by $fieldValue->hasContent or something similar, not decided yet
                    if ( $fieldDefinition->isRequired && empty( $fieldValue ) )
                    {
                        throw new ContentValidationException( '@TODO: What error code should be used?' );
                    }

                    if ( !$this->validateField( $fieldDefinition, $fieldType, $fieldValue ) )
                    {
                        $areFieldsValid = false;
                    }

                    if ( !$areFieldsValid ) continue;

                    $spiFields[] = new SPIField(
                        array(
                            "id"                => null,
                            "fieldDefinitionId" => $fieldDefinition->identifier,
                            "type"              => $fieldDefinition->fieldTypeIdentifier,
                            "value"             => $this->buildSPIFieldValue( $fieldValue, $fieldDefinition, $fieldType ),
                            "language"          => $languageCode,
                            "version"           => null
                        )
                    );
                }
            }
        }

        // @TODO: improvised, revisit when ContentFieldValidationException is implemented
        if ( !$areFieldsValid )
        {
            $errors = array();
            foreach ( $validators as $validator )
            {
                $errors[] = $validator->getMessage();
            }
            throw new ContentFieldValidationException( $errors );
        }

        $modifiedTimestamp = $contentCreateStruct->modificationDate ? $contentCreateStruct->modificationDate->getTimestamp() : time();

        $spiContentCreateStruct = new SPIContentCreateStruct(
            array(
                //"name" => ,
                "typeId"            => $contentCreateStruct->contentType->id,
                "sectionId"         => $contentCreateStruct->sectionId,
                "ownerId"           => $contentCreateStruct->ownerId,
                "locations"         => $locationCreateStructs,
                "fields"            => $spiFields,
                "alwaysAvailable"   => $contentCreateStruct->alwaysAvailable,
                "remoteId"          => $remoteId,
                "initialLanguageId" => $contentCreateStruct->mainLanguageCode,
                //"published" => ,
                "modified"          => $modifiedTimestamp
            )
        );

        $spiContent = $this->persistenceHandler->contentHandler()->create( $spiContentCreateStruct );

        return $this->buildContentDomainObject( $spiContent );
    }

    /**
     * Validates a field against validators from FieldDefinition
     * @todo hints
     *
     * @param $fieldDefinition
     * @param $fieldType
     * @param $fieldValue
     *
     * @return bool
     */
    protected function validateField( $fieldDefinition, $fieldType, $fieldValue )
    {
        $areFieldsValid = true;

        foreach ( $fieldDefinition->getValidators() as $validator )
        {
            foreach ( $fieldType->allowedValidators() as $allowedValidatorClass )
            {
                if ( $validator instanceOf $allowedValidatorClass )
                {
                    if ( $validator->validate( $fieldValue ) ) $areFieldsValid = false;
                    $validators[] = $validator;
                }
            }
        }

        return $areFieldsValid;
    }

    /**
     * Builds SPIValue object
     * @todo hints when fields are refactored
     *
     * @param $fieldValue
     * @param \eZ\Publish\API\Repository\Values\ContentType\FieldDefinition $fieldDefinition
     * @param $fieldType
     *
     * @return \eZ\Publish\SPI\Persistence\Content\FieldValue
     */
    protected function buildSPIFieldValue( $fieldValue, FieldDefinition $fieldDefinition, $fieldType )
    {
        return new SPIFieldValue(
            array(
                "data"          => $fieldValue,
                "fieldSettings" => $fieldDefinition->getFieldSettings(),
                // @todo atm this is also set in converter
                "sortKey"       => $fieldType->getSortInfo( $fieldValue )
            )
        );
    }

    /**
     * Updates the metadata.
     *
     * (see {@link ContentMetadataUpdateStruct}) of a content object - to update fields use updateContent
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to update the content meta data
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if the remoteId in $contentMetadataUpdateStruct is set but already exists
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param \eZ\Publish\API\Repository\Values\Content\ContentMetadataUpdateStruct $contentMetadataUpdateStruct
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content the content with the updated attributes
     */
    public function updateContentMetadata( APIContentInfo $contentInfo, ContentMetaDataUpdateStruct $contentMetadataUpdateStruct )
    {

    }

    /**
     * deletes a content object including all its versions and locations including their subtrees.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowd to delete the content (in one of the locations of the given content object)
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     */
    public function deleteContent( APIContentInfo $contentInfo )
    {

    }

    /**
     * creates a draft from a published or archived version.
     *
     * If no version is given, the current published version is used.
     * 4.x: The draft is created with the initialLanguage code of the source version or if not present with the main language.
     * It can be changed on updating the version.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to create the draft
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\API\Repository\Values\User\User $user if set given user is used to create the draft - otherwise the current user is used
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content - the newly created content draft
     */
    public function createContentDraft( APIContentInfo $contentInfo, APIVersionInfo $versionInfo = null, User $user = null )
    {

    }

    /**
     * Load drafts for a user.
     *
     * If no user is given the drafts for the authenticated user a returned
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to load the draft list
     *
     * @param \eZ\Publish\API\Repository\Values\User\User $user
     *
     * @return \eZ\Publish\API\Repository\Values\Content\VersionInfo the drafts ({@link VersionInfo}) owned by the given user
     */
    public function loadContentDrafts( User $user = null )
    {

    }


    /**
     * Translate a version
     *
     * updates the destination version given in $translationInfo with the provided translated fields in $translationValues
     *
     * @example Examples/translation_5x.php
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to update this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the given destination version is not a draft
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException if a required field is set to an empty value
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException if a field in the $translationValues is not valid
     *
     * @param \eZ\Publish\API\Repository\Values\Content\TranslationInfo $translationInfo
     * @param \eZ\Publish\API\Repository\Values\Content\TranslationValues $translationValues
     * @param \eZ\Publish\API\Repository\Values\User\User $user If set, this user is taken as modifier of the version
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content the content draft with the translated fields
     *
     * @since 5.0
     */
    public function translateVersion( TranslationInfo $translationInfo, APITranslationValues $translationValues, User $user = null )
    {

    }

    /**
     * Updates the fields of a draft.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to update this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is not a draft
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException if a field in the $contentUpdateStruct is not valid
     * @throws \eZ\Publish\API\Repository\Exceptions\ContentValidationException if a required field is set to an empty value
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct $contentUpdateStruct
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content the content draft with the updated fields
     */
    public function updateContent( APIVersionInfo $versionInfo, APIContentUpdateStruct $contentUpdateStruct )
    {

    }

    /**
     * Publishes a content version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to publish this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is not a draft
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to publish this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is not a draft
     */
    public function publishVersion( APIVersionInfo $versionInfo )
    {

    }

    /**
     * removes the given version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is in state published
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to remove this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     */
    public function deleteVersion( APIVersionInfo $versionInfo )
    {

    }

    /**
     * Loads all versions for the given content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to list versions
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     *
     * @return \eZ\Publish\API\Repository\Values\Content\VersionInfo[] an array of {@link \eZ\Publish\API\Repository\Values\Content\VersionInfo} sorted by creation date
     */
    public function loadVersions( APIContentInfo $contentInfo )
    {

    }

    /**
     * copies the content to a new location. If no version is given,
     * all versions are copied, otherwise only the given version.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to copy the content to the given location
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param \eZ\Publish\API\Repository\Values\Content\LocationCreateStruct $destinationLocationCreateStruct the target location where the content is copied to
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function copyContent( APIContentInfo $contentInfo, LocationCreateStruct $destinationLocationCreateStruct, APIVersionInfo $versionInfo = null)
    {

    }

    /**
     * finds content objects for the given query.
     *
     * @TODO define structs for the field filters
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Query $query
     * @param array  $fieldFilters - a map of filters for the returned fields.
     *        Currently supported: <code>array("languages" => array(<language1>,..))</code>.
     * @param boolean $filterOnUserPermissions if true only the objects which is the user allowed to read are returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\SearchResult
     */
    public function findContent( Query $query, array $fieldFilters,  $filterOnUserPermissions = true )
    {

    }

    /**
     * Performs a query for a single content object
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to read the found content object
     * @TODO throw an exception if the found object count is > 1
     *
     * @TODO define structs for the field filters
     * @param \eZ\Publish\API\Repository\Values\Content\Query $query
     * @param array  $fieldFilters - a map of filters for the returned fields.
     *        Currently supported: <code>array("languages" => array(<language1>,..))</code>.
     * @param boolean $filterOnUserPermissions if true only the objects which is the user allowed to read are returned.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\SearchResult
     */
    public function findSingle( Query $query, array $fieldFilters, $filterOnUserPermissions = true )
    {

    }

    /**
     * load all outgoing relations for the given version
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to read this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $versionInfo
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Relation[] an array of {@link Relation}
     */
    public function loadRelations( APIVersionInfo $versionInfo )
    {

    }

    /**
     * Loads all incoming relations for a content object.
     *
     * The relations come only
     * from published versions of the source content objects
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to read this version
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Relation[] an array of {@link Relation}
     */
    public function loadReverseRelations( APIContentInfo $contentInfo )
    {

    }

    /**
     * Adds a relation of type common.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed to edit this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is not a draft
     *
     * The source of the relation is the content and version
     * referenced by $versionInfo.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $sourceVersion
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $destinationContent the destination of the relation
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Relation the newly created relation
     */
    public function addRelation( APIVersionInfo $sourceVersion, APIContentInfo $destinationContent )
    {

    }

    /**
     * Removes a relation of type COMMON from a draft.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed edit this version
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException if the version is not a draft
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if there is no relation of type COMMON for the given destination
     *
     * @param \eZ\Publish\API\Repository\Values\Content\VersionInfo $sourceVersion
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $destinationContent
     */
    public function deleteRelation( APIVersionInfo $sourceVersion, APIContentInfo $destinationContent)
    {

    }

    /**
     * add translation information to the content object
     *
     * @example Examples/translation_5x.php
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed add a translation info
     *
     * @param \eZ\Publish\API\Repository\Values\Content\TranslationInfo $translationInfo
     *
     * @since 5.0
     */
    public function addTranslationInfo( TranslationInfo $translationInfo )
    {

    }

    /**
     * lists the translations done on this content object
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException if the user is not allowed read translation infos
     *
     * @param \eZ\Publish\API\Repository\Values\Content\ContentInfo $contentInfo
     * @param array $filter
     * @todo TBD - filter by sourceversion destination version and languages
     *
     * @return \eZ\Publish\API\Repository\Values\Content\TranslationInfo[] an array of {@link TranslationInfo}
     *
     * @since 5.0
     */
    public function loadTranslationInfos( APIContentInfo $contentInfo, array $filter = array() )
    {

    }

    /**
     * Instantiates a new content create struct object
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentType $contentType
     * @param string $mainLanguageCode
     *
     * @return \eZ\Publish\API\Repository\Values\Content\ContentCreateStruct
     */
    public function newContentCreateStruct( ContentType $contentType, $mainLanguageCode )
    {
        if ( !is_string( $mainLanguageCode ) )
        {
            throw new InvalidArgumentException(
                '$mainLanguageCode',
                "must be string"
            );
        }

        return new ContentCreateStruct(
            array(
                "contentType"      => $contentType,
                "mainLanguageCode" => $mainLanguageCode
            )
        );
    }

    /**
     * Instantiates a new content meta data update struct
     *
     * @return \eZ\Publish\API\Repository\Values\Content\ContentMetadataUpdateStruct
     */
    public function newContentMetadataUpdateStruct()
    {
        return new ContentMetaDataUpdateStruct();
    }

    /**
     * Instantiates a new content update struct
     * @return \eZ\Publish\API\Repository\Values\Content\ContentUpdateStruct
     */
    public function newContentUpdateStruct()
    {
        return new ContentUpdateStruct();
    }

    /**
     * Instantiates a new TranslationInfo object
     * @return \eZ\Publish\API\Repository\Values\Content\TranslationInfo
     */
    public function newTranslationInfo()
    {
        return new TranslationInfo();
    }

    /**
     * Instantiates a Translation object
     * @return \eZ\Publish\API\Repository\Values\Content\TranslationValues
     */
    public function newTranslationValues()
    {
        return new TranslationValues();
    }
}
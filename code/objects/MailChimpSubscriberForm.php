<?php

use Mailchimp\MailchimpLists;

/**
 * Class MailChimpSubscriberForm
 *
 * @property string $ListID
 * @property string $FieldsToShow
 * @property string $InterestCategoriesToShow
 */
class MailChimpSubscriberForm extends DataObject implements PermissionProvider
{

    /**
     * @var array
     */
    private static $db = [
        'Title'                    => 'Varchar(255)',
        'ListID'                   => 'Varchar(255)',
        'FieldsToShow'             => 'Text',
        'InterestCategoriesToShow' => 'Text',
    ];

    /**
     * @var array
     */
    private static $has_many = [
        'MailChimpPages' => 'MailChimpPage',
    ];

    /**
     * @var array
     */
    private static $field_type_map = [
        'text'       => 'TextField',
        'checkboxes' => 'CheckboxSetField',
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $remove  = [
            'FieldsToShow',
            'InterestCategoriesToShow',
        ];

        $fields->removeByName($remove);

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create('Title')
                ->setTitle('Title')
        );

        $lists = [];

        foreach ($this->getLists()->lists as $list) {
            $lists[$list->id] = $list->name;
        }

        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create('ListID')
                ->setTitle('MailChimp List')
                ->setSource($lists)
                ->setEmptyString('Select List')
        );

        if ($this->ID > 0) {
            $fieldsSource = [];

            $pushFieldOption = function ($field) use (&$fieldsSource) {
                $fieldsSource[$field->tag] = $field->name;
            };

            if (count($this->getMergeFields()->merge_fields) > 0) {
                foreach ($this->getMergeFields()->merge_fields as $field) {
                    $pushFieldOption($field);
                }
                $fields->addFieldToTab(
                    'Root.Main',
                    CheckboxSetField::create('FieldsToShow')
                        ->setTitle('Fields To Show')
                        ->setSource($fieldsSource)
                );
            }

            $interestCategories = $this->getInterestCategories()->categories;
            if (count($interestCategories) > 0) {
                $categoryOptions = [];

                $pushCategoryOption = function ($category) use (&$categoryOptions) {
                    $categoryOptions[$category->id] = $category->title;
                };

                foreach ($interestCategories as $category) {
                    $pushCategoryOption($category);
                }
                $fields->addFieldToTab(
                    'Root.Main',
                    CheckboxSetField::create('InterestCategoriesToShow')
                        ->setTitle('Groups To Show')
                        ->setSource($categoryOptions)
                );
            }

        }

        return $fields;
    }

    /**
     * @return RequiredFields
     */
    public function getCMSValidator()
    {
        return new RequiredFields([
            'ListID'
        ]);
    }

    /**
     * @var
     */
    private $mail_chimp;

    /**
     * @return $this
     */
    public function setMailChimp()
    {
        $this->mail_chimp = new MailchimpLists(Config::inst()->get('MailChimpController', 'apikey'));

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMailChimp()
    {
        if ( ! $this->mail_chimp) {
            $this->setMailChimp();
        }

        return $this->mail_chimp;
    }

    /**
     * @return object
     */
    protected function getLists()
    {
        $mailChimp = $this->getMailChimp();

        return $mailChimp->getLists();
    }

    /**
     * @return mixed
     */
    protected function getMergeFields()
    {
        $mailChimp = $this->getMailChimp();

        return $mailChimp->getMergeFields($this->ListID);
    }

    /**
     * @return mixed
     */
    protected function getInterestCategories()
    {
        $mailChimp = $this->getMailChimp();

        return $mailChimp->getInterestCategories($this->ListID);
    }

    /**
     * @param $interestCategoryID
     *
     * @return mixed
     */
    protected function getInterests($interestCategoryID)
    {
        $mailChimp = $this->getMailChimp();

        return $mailChimp->getInterests($this->ListID, $interestCategoryID);
    }


    public function getMergeFormFields()
    {
        $remoteFields = $this->getMergeFields();

        $localFields = [];
        $data        = explode(',', $this->FieldsToShow);

        foreach ($remoteFields->merge_fields as $field) {
            if (in_array($field->tag, $data)) {
                if ($field !== false) {
                    $localFields[] = $this->getBuiltField($field);
                }
            }
        }

        return $localFields;
    }

    /**
     * @return array
     */
    public function getInterestCategoryFields()
    {
        $remoteCategories = $this->getInterestCategories();

        $localCategories = explode(',', $this->InterestCategoriesToShow);

        $localFields = [];

        foreach ($remoteCategories->categories as $category) {
            if (in_array($category->id, $localCategories)) {
                $localFields[] = $this->getBuiltField($category, true);
            }
        }

        return $localFields;
    }

    /**
     * @param $field
     * @param bool $isCategory
     *
     * @return bool
     */
    protected function getBuiltField($field, $isCategory = false)
    {
        $fieldTypeMap = $this->config()->get('field_type_map');
        if (array_key_exists($field->type, $fieldTypeMap)) {
            if ( ! $isCategory) {
                $fieldType = $fieldTypeMap[$field->type];
                $field     = $fieldType::create($field->tag)->setTitle($field->name);
            } else {
                $interests = $this->getInterests($field->id);
                $fieldType = $fieldTypeMap[$field->type];
                $field     = $fieldType::create("Category[{$field->id}]")->setTitle($field->title);
                if ($field instanceof CheckboxSetField) {
                    $field->setSource($this->getInterestArray($interests));
                }
            }

            return $field;
        }

        return false;
    }

    /**
     * @param $interests
     *
     * @return array
     */
    protected function getInterestArray($interests)
    {
        $interestsArray = [];

        foreach ($interests as $interest) {
            if (is_array($interest)) {
                foreach ($interest as $subInterest) {
                    if (property_exists($subInterest, 'id')) {
                        $interestsArray[$subInterest->id] = $subInterest->name;
                    }
                }
            }
        }

        return $interestsArray;
    }

    /**
     * @return array
     */
    public function providePermissions()
    {
        return [
            'MailChimp_create' => 'Create MailChimp Form',
            'MailChimp_edit'   => 'Edit MailChimp Form',
            'MailChimp_delete' => 'Delete MailChimp Form',
        ];
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canCreate($member = null)
    {
        if ($member === null) {
            $member = Member::currentUser();
        }

        return Permission::check('MailChimp_create', 'any', $member);
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canEdit($member = null)
    {
        if ($member === null) {
            $member = Member::currentUser();
        }

        return Permission::check('MailChimp_edit', 'any', $member);
    }

    /**
     * @param null $member
     *
     * @return bool|int
     */
    public function canDelete($member = null)
    {
        if ($member === null) {
            $member = Member::currentUser();
        }

        return Permission::check('MailChimp_delete', 'any', $member);
    }

    /**
     * @param null $member
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return true;
    }

}
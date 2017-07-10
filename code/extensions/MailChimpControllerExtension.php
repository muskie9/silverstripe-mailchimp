<?php

/**
 * Class MailChimpControllerExtension
 */
class MailChimpControllerExtension extends Extension
{

    /**
     * @var array
     */
    private static $allowed_actions = [
        'McSubscribeForm',
        'success',
        'error',
    ];

    /**
     * Create the subscription form
     *
     * @return \Form|bool
     */
    public function McSubscribeForm()
    {
        if ( ! $this->owner->data()->MailChimpFormID > 0 || ! MailChimpSubscriberForm::get()->byID($this->owner->data()->MailChimpFormID)) {
            return false;
        }
        $listID = $this->owner->data()->MailChimpForm()->ListID;

        $formData = MailChimpSubscriberForm::get()->filter('ListID', $listID)->first();

        $fieldsArr   = array();
        $fieldsArr[] = HiddenField::create('ListID')->setValue($listID);

        if (count($formData->getMergeFormFields()) > 0) {
            $fieldsArr = array_merge($fieldsArr, $formData->getMergeFormFields());
        }

        $email = EmailField::create('Email')->setTitle('Email');
        array_push($fieldsArr, $email);

        if (count($formData->getInterestCategoryFields()) > 0) {
            $fieldsArr = array_merge($fieldsArr, $formData->getInterestCategoryFields());
        }

        $fields   = new FieldList($fieldsArr);
        $actions  = new FieldList(
            new FormAction('McDoSubscribeForm', 'Send Now')
        );
        $required = new RequiredFields(
            array('Email')
        );

        $form = new Form($this->owner, 'McSubscribeForm', $fields, $actions, $required);

        $this->owner->extend('updateMcSubscribeForm', $form);

        return $form;
    }

    /**
     * Process the form
     *
     * @param $data
     * @param Form $form
     *
     * @return bool
     */
    public function McDoSubscribeForm($data, Form $form)
    {
        $dataClone = $data;

        $email = $data['Email'];
        unset($dataClone['Email']);
        unset($dataClone['url']);
        unset($dataClone['SecurityID']);
        unset($dataClone['action_McDoSubscribeForm']);

        $mergeFields = [];
        foreach ($dataClone as $field => $val) {
            if ($field != 'Category') {
                $mergeFields[$field] = $val;
                unset($dataClone[$field]);
            }
        }

        $this->owner->extend('updateMergeFields', $mergeFields);

        $interests = [];

        foreach ($dataClone['Category'] as $interest => $subInterests) {
            if (is_array($subInterests)) {
                foreach ($subInterests as $interest) {
                    $interests[$interest] = true;
                }
            } else {
                $interests[$subInterests] = true;
            }
        }

        return $this->mailChimpSubscribe($email, $mergeFields, $interests);
    }

    /**
     * Store the user in MailChimp
     *
     * @param String $email
     * @param array $mergeFields
     * @param array $interests
     *
     * @return Boolean
     */
    protected function mailChimpSubscribe($email, $mergeFields = [], $interests = [])
    {

        $form       = $this->owner->data()->MailChimpForm();
        $parameters = array_merge(['status' => 'subscribed'], ['merge_fields' => $mergeFields],
            ['interests' => $interests]);

        $response = $form->subscribe($email, $parameters);

        if (is_object($response) && property_exists($response, 'id')) {
            return $this->owner->redirect($this->owner->Link('success'));
        } else {
            return $this->owner->redirect($this->owner->Link('error'));
        }
    }

    /**
     * @return mixed
     */
    public function success()
    {
        return $this->owner->customise([
            'Title'           => $this->owner->data()->Title,
            'Content'         => $this->owner->data()->MailChimpForm()->Success,
            'McSubscribeForm' => false,
        ]);
    }

    /**
     * @return mixed
     */
    public function error()
    {
        return $this->owner->customise([
            'Title'           => $this->owner->data()->Title,
            'Content'         => $this->owner->data()->MailChimpForm()->Error,
            'McSubscribeForm' => false,
        ]);
    }

}

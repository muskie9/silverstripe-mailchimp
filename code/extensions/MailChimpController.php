<?php

use MailChimp\MailchimpLists;

class MailChimpControllerExtension extends Extension
{

    private static $allowed_actions = array('McSubscribeForm');

    /*
     * Redirect after registration
     */
    private static $redirect = true;

    /*
     * URL to redirect after a succesful registration
     */
    private static $redirect_ok = 'reg-ok';

    /*
     * URL to redirect after a failing registration
     */
    private static $redirect_ko = 'reg-ko';

    /**
     * Create the subscription form
     *
     * @return \Form|bool
     */
    public function McSubscribeForm()
    {
        if ( ! $this->owner->data()->MailChimpFormID > 0 || !MailChimpSubscriberForm::get()->byID($this->owner->data()->MailChimpFormID)) {
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
     * @param type $data
     * @param type $form
     *
     * @return type
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
            foreach($subInterests as $interest){
                $interests[$interest] = true;
            }
        }

        $regOk = $this->mailChimpSubscribe($email, $mergeFields, $interests);
        if ($regOk) {

            // Pulisco la sessione 
            Session::clear('MAILCHIMP_ERRCODE');
            Session::clear('MAILCHIMP_ERRMSG');

            if (Config::inst()->get('MailChimpController', 'redirect')) {
                // Redireziono alla pagina di avvenuta registrazione
                return $this->redirect(Config::inst()->get('MailChimpController', 'redirect_ok'));
            } else {
                // Se non è definita una pagina di redirezione, rimando indietro
                return $this->redirectBack();
            }
        } else {
            // Pagina di errore
            return $this->redirect(Config::inst()->get('MailChimpController', 'redirect_ko'));
        }
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

        $form = $this->owner->data()->MailChimpForm();
        $parameters = array_merge(['email_address' => $email, 'status' => 'subscribed'], $mergeFields, $interests);
        //$retVal = $api->addMember(Config::inst()->get('MailChimpController', 'listid'), $email, $merge_vars);

        // Pulisco la sessione 
        Session::clear('MAILCHIMP_ERRCODE');
        Session::clear('MAILCHIMP_ERRMSG');

        // Gestione errori
        if ($api->errorCode) {
            // Errori in sessione
            Session::set('MAILCHIMP_ERRCODE', $api->errorCode);
            Session::set('MAILCHIMP_ERRMSG', $api->errorMessage);
            trigger_error("Error subscribing: email [$email] code [$api->errorCode] msg[$api->errorMessage]",
                E_USER_WARNING);
        }

        return $retVal;
    }

    protected function getListTopics()
    {

    }

}

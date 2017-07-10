<?php

/**
 * Class MailChimpFormAdmin
 */
class MailChimpFormAdmin extends ModelAdmin
{

    /**
     * @var string
     */
    private static $url_segment = 'mailchimp-forms';

    /**
     * @var string
     */
    private static $menu_title = 'MailChimp Forms';

    /**
     * @var array
     */
    private static $managed_models = [
        'MailChimpSubscriberForm',
    ];

}
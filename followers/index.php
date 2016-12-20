<?php
/**
 * 
 * 
 *
 *
 * 
 * 
 *
 * 
 * 
 * 
 * 
 *
 * 
 * 
 * 
 * 
 *
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 *
 * 
 * 
 * 
 * 
 * 
 * 
 * 
 *
 * @copyright 2016 I M Dz And I Speak English
 */

// make sure we are not being called directly
defined('APP_DIR') or exit();

//------------------------------
// get_followed
//------------------------------
function view_Follower_get_followed($_post, $_user, $_conf)
{
    if (!User_is_logged_in()) {
        $_rs = array('error' => 'not logged in');
        Core_json_response($_rs);
    }
    // Make sure user is a follower
    $_rs = array('following' => array());
    $_rt = array(
        'search'        => array(
            "_user_id = {$_user['_user_id']}",
        ),
        'return_keys'   => array('follow_profile_id', 'follow_active'),
        'skip_triggers' => true,
        'privacy_check' => false,
        'limit'         => 5000
    );
    $_rt = Core_db_search_items('Follower', $_rt);
    if ($_rt && is_array($_rt) && isset($_rt['_items'])) {
        // We are following profiles - return
        foreach($_rt['_items'] as $_f) {
            $_rs['following']["{$_f['follow_profile_id']}"] = $_f['follow_active'];
        }
    }
    jrCore_json_response($_rs);
    exit;
}

//------------------------------
// follow
//------------------------------
function view_Follower_follow($_post, $_user, $_conf)
{
    // [_uri] => /fans/follow/5/__ajax=1
    // [module_url] => fans
    // [module] => Follower
    // [option] => follow
    // [_1] => 5 (profile_id to be followed)
    // [__ajax] => 1

    User_session_require_login();
    Core_validate_location_url();

    if (!isset($_post['_1']) || !Core_checktype($_post['_1'], 'number_nz')) {
        $_rs = array('error' => 'invalide profile_id-please try again');
        Core_json_response($_rs);
    }

    $_ln = User_load_lang_strings();
    $pid = (int) $_post['_1'];

    // First - see if this user is already following
    $_rt = Follower_is_follower($_user['_user_id'], $pid);
    if ($_rt) {
        // User is already a follower
        $_rs = array('OK' => 1, 'VALUE' => $_ln['Follower'][2]);
        Core_json_response($_rs);
    }

    // We need to see if this profile is requiring approval before
    // any new follower can join up.
    $_pi = Core_db_get_item('Profile', $pid);
    $act = 1;
    if (isset($_pi['profile_Follower_approve']) && $_pi['profile_Follower_approve'] == 'on') {
        $act = 0;
    }

    // Create our new following entry
    $_dt = array(
        'follow_profile_id' => $pid,
        'follow_active'     => $act
    );
    $_cr = array(
        '_profile_id' => User_get_profile_home_key('_profile_id')
    );
    $fid = Core_db_create_item('Follower', $_dt, $_cr, false);
    if (isset($fid) && Core_checktype($fid, 'number_nz')) {
        $_owners = Profile_get_owner_info($pid);
        // If we are not active...
        if ($act === 0) {
            // Please feel free to Send out an email to profile owners letting them know of the new follower
            if (isset($_owners) && is_array($_owners)) {
                $_rp = array(
                    'system_name'          => $_conf['Core_system_name'],
                    'follower_name'        => $_user['user_name'],
                    'follower_url'         => "{$_conf['Core_base_url']}/" . User_get_profile_home_key('profile_url'),
                    'approve_follower_url' => "{$_conf['Core_base_url']}/{$_post['module_url']}/browse/{$pid}/{$_pi['profile_url']}",
                    '_profile'             => $_pi
                );
                list($sub, $msg) = Core_parse_email_templates('Follower', 'approve', $_rp);
                foreach ($_owners as $_o) {
                    User_notify($_o['_user_id'], 0, 'jrFollower', 'follower_pending', $sub, $msg);
                }
            }
            $_rs = array('PENDING' => 1, 'VALUE' => $_ln['Follower'][5]);
            Core_json_response($_rs);
        }
        else {
            if (isset($_owners) && is_array($_owners)) {
                $_rp = array(
                    'profile_url'          => $_pi['profile_url'],
                    'system_name'          => $_conf['Core_system_name'],
                    'follower_name'        => $_user['user_name'],
                    'follower_profile_url' => "{$_conf['Core_base_url']}/" . User_get_profile_home_key('profile_url'),
                    'follower_browse_url'  => "{$_conf['Core_base_url']}/{$_post['module_url']}/browse/{$pid}/{$_pi['profile_url']}",
                    '_profile'             => $_pi
                );
                list($sub, $msg) = Core_parse_email_templates('Follower', 'new_follower', $_rp);
                foreach ($_owners as $_o) {
                    User_notify($_o['_user_id'], 0, 'Follower', 'new_follower', $sub, $msg);
                }
            }

            // Increment Profile counts...
            Core_db_increment_key('Profile', $pid, 'profile_Follower_item_count', 1);

            // Add to Actions...
            Core_run_module_function('Action_save', 'create', 'Follower', $fid, $_pi, false, User_get_profile_home_key('_profile_id'));
            Profile_reset_cache($pid);
            Profile_reset_cache(User_get_profile_home_key('_profile_id'));
            $_rs = array('OK' => 1, 'VALUE' => $_ln['Follower'][2]);
            Core_json_response($_rs);
        }
    }
    $_rs = array('error' => 'unable to create follow request - please try again');
    Core_json_response($_rs);
}

//------------------------------
// unfollow
//------------------------------
function view_Follower_unfollow($_post, $_user, $_conf)
{
    // [_uri] => /fans/unfollow/5/__ajax=1
    // [module_url] => fans
    // [module] => Follower
    // [option] => follow
    // [_1] => 5 (profile_id to no longer followed)
    // [__ajax] => 1
    User_session_require_login();
    Core_validate_location_url();

    $_ln = User_load_lang_strings();
    $pid = (int) $_post['_1'];

    // Make sure user is a follower
    $_rt = Follower_is_follower($_user['_user_id'], $pid);
    if ($_rt) {
        // If this follower is ACTIVE, we need to decrement follower counts
        if (isset($_rt['follow_active']) && $_rt['follow_active'] == '1') {
            Core_db_decrement_key('Profile', $pid, 'profile_Follower_item_count', 1);
        }
        Profile_reset_cache($pid);
        Core_db_delete_item('Follower', $_rt['_item_id'], true, false);
    }
    $_rs = array('OK' => 1, 'VALUE' => $_ln['Follower'][1]);
    Core_json_response($_rs);
}

//------------------------------
// browse
//------------------------------
function view_Follower_browse($_post, $_user, $_conf)
{
    User_session_require_login();
    $_ln = User_load_lang_strings();

    $pid = $_user['user_active_profile_id'];
    $prn = $_user['profile_name'];
    if (isset($_post['_1']) && Core_checktype($_post['_1'], 'number_nz') && Profile_is_profile_owner($_post['_1']) && $_post['_1'] != $pid) {
        $pid = (int) $_post['_1'];
        $prn = Core_db_get_item_key('Profile', $pid, 'profile_name');
    }
    Core_page_banner("{$prn} - {$_ln['Follower'][25]}");

    $_sc = array(
        'search'                       => array(
            "follow_profile_id = {$pid}"
        ),
        'pagebreak'                    => 12,
        'page'                         => 1,
        'order_by'                     => array(
            '_created' => 'numerical_desc'
        ),
        'exclude_Profile_quota_keys' => true,
        'privacy_check'                => false,
        'ignore_pending'               => true,
        'no_cache'                     => true
    );
    if (isset($_COOKIE['core_pager_rows']) && Core_checktype($_COOKIE['core_pager_rows'], 'number_nz')) {
        $_sc['pagebreak'] = (int) $_COOKIE['core_pager_rows'];
    }
    if (isset($_post['p']) && Core_checktype($_post['p'], 'number_nz')) {
        $_sc['page'] = (int) $_post['p'];
    }
    $_us = Core_db_search_items('Follower', $_sc);
    $_ln = User_load_lang_strings();

    $dat             = array();
    $dat[1]['title'] = '&nbsp;';
    $dat[1]['width'] = '3%';
    $dat[2]['title'] = $_ln['Follower'][27]; // 'user name'
    $dat[2]['width'] = '31%';
    $dat[3]['title'] = $_ln['Follower'][28]; // 'profile name'
    $dat[3]['width'] = '31%';
    $dat[4]['title'] = $_ln['Follower'][29]; // 'follower since'
    $dat[4]['width'] = '25%';
    $dat[5]['title'] = $_ln['Follower'][30]; // 'approve'
    $dat[5]['width'] = '5%';
    $dat[6]['title'] = $_ln['Follower'][31]; // 'delete'
    $dat[6]['width'] = '5%';
    Core_page_table_header($dat);

    if (isset($_us['_items']) && is_array($_us['_items'])) {

        foreach ($_us['_items'] as $_usr) {
            $dat             = array();
            $_im             = array(
                'crop'  => 'auto',
                'alt'   => $_usr['user_name'],
                'title' => $_usr['user_name'],
                '_v'    => (isset($_usr['user_image_time']) && $_usr['user_image_time'] > 0) ? $_usr['user_image_time'] : false
            );
            $dat[1]['title'] = Image_get_image_src('User', 'user_image', $_usr['_user_id'], 'xsmall', $_im);
            $dat[2]['title'] = '<h3>' . $_usr['user_name'] . '</h3>';
            $dat[2]['class'] = 'center';
            $dat[3]['title'] = "{$_usr['profile_name']}&nbsp;&nbsp;(<a href=\"{$_conf['jrCore_base_url']}/{$_usr['profile_url']}\">@{$_usr['profile_url']}</a>)";
            $dat[3]['class'] = 'center';
            $dat[4]['title'] = Core_format_time($_usr['_created']);
            $dat[4]['class'] = 'center';
            if (isset($_usr['follow_active']) && $_usr['follow_active'] == '0') {
                $dat[5]['title'] = Core_page_button("a{$_usr['_user_id']}", 'approve', "Core_window_location('{$_conf['Core_base_url']}/{$_post['module_url']}/approve/{$pid}/{$_usr['_user_id']}')");
                $dat[5]['class'] = 'center error';
            }
            else {
                $dat[5]['title'] = '-';
                $dat[5]['class'] = 'center';
            }
            $dat[6]['title'] = Core_page_button("d{$_usr['_user_id']}", 'delete', "if(confirm('" . addslashes($_ln['Follower'][33]) . "')){ Core_window_location('{$_conf['Core_base_url']}/{$_post['module_url']}/delete/{$pid}/{$_usr['_user_id']}' )}");
            Core_page_table_row($dat);
        }
        Core_page_table_pager($_us);
    }
    else {
        $dat             = array();
        $dat[1]['title'] = "<p>{$_ln['Follower'][32]}</p>";
        $dat[1]['class'] = 'center';
        Core_page_table_row($dat);
    }
    Core_page_table_footer();
    Core_page_display();
}

//------------------------------
// approve
//------------------------------
function view_Follower_approve($_post, $_user, $_conf)
{
    // [_uri] => /fans/approve/1/5/__ajax=1
    // [module_url] => fans
    // [module] => Follower
    // [option] => follow
    // [_1] => 1 (profile_id)
    // [_2] => 5 (user_id being approved)
    // [__ajax] => 1
    User_session_require_login();
    Core_validate_location_url();
    User_load_lang_strings();

    $pid = (int) $_post['_1'];
    $uid = (int) $_post['_2'];

    // Make sure this user has access to this profile
    if (!Profile_is_profile_owner($pid)) {
        User_not_authorized();
    }

    // Make sure follow exists...
    $_rt = Follower_is_follower($uid, $pid);
    if (!$_rt) {
        Core_notice_page('error', 'User does not appear to have a follower entry - please try again Later');
    }
    $_us = jrCore_db_get_item('User', $uid);
    $_dt = array(
        'follow_active' => 1
    );
    Core_db_update_item('Follower', $_rt['_item_id'], $_dt);

    // Increment Profile counts...
    Core_db_increment_key('Profile', $pid, 'profile_Follower_item_count', 1);

    // Get profile info of user that we just approved
    $_pr = jrCore_db_get_item('Profile', $pid);

    // We only send the email on first activation
    $_rp = array(
        'profile_name' => $_pr['profile_name'],
        'profile_url'  => "{$_conf['Core_base_url']}/{$_pr['profile_url']}"
    );
    list($sub, $msg) = Core_parse_email_templates('follower', 'follower_approved', $_rp);
    User_notify($uid, 0, 'Follower', 'follow_approved', $sub, $msg);
    Core_delete_all_cache_entries('Follower', $_user['_user_id']);

    // Add action
    $_save = array(
        '_user_id' => $uid
    );
    Core_set_flag('follower_approved', $_save);
    Core_run_module_function('Action_save', 'create', 'Follower', $_rt['_item_id'], $_pr, false, $_us['_profile_id']);

    Profile_reset_cache($_us['_profile_id']);
    Profile_reset_cache($pid);
    Core_location('referrer');
}

//------------------------------
// delete
//------------------------------
function view_Follower_delete($_post, $_user, $_conf)
{
    // [_uri] => /fans/delete/1/5/__ajax=1
    // [module_url] => fans
    // [module] => Follower
    // [option] => follow
    // [_1] => 1 (profile_id)
    // [_2] => 5 (user_id being approved)
    // [__ajax] => 1
    User_session_require_login();
    Core_validate_location_url();

    $pid = (int) $_post['_1'];
    $uid = (int) $_post['_2'];

    // Make sure this user has access to this profile
    if (!Profile_is_profile_owner($pid)) {
        User_not_authorized();
    }

    // Make sure follow exists...
    $_rt = Follower_is_follower($uid, $pid);
    if ($_rt) {
        // If this follower is ACTIVE, we need to decrement follower counts
        if (isset($_rt['follow_active']) && $_rt['follow_active'] == '1') {
            Core_db_decrement_key('Profile', $pid, 'profile_Follower_item_count', 1);
        }
        Core_db_delete_item('Follower', $_rt['_item_id']);
    }
    Profile_reset_cache($pid);
    Core_location("{$_conf['Core_base_url']}/{$_user['profile_url']}/{$_post['module_url']}");
}

//------------------------------
// integrity_check
//------------------------------
function view_Follower_integrity_check($_post, $_user, $_conf)
{
    User_master_only();
    Core_page_include_admin_menu();
    Core_page_admin_tabs('Follower');
    Core_page_banner("Integrity Check");

    // Form init
    $_tmp = array(
        'submit_value'  => 'run integrity check',
        'cancel'        => 'referrer',
        'submit_prompt' => 'Are you sure you want to run the Profile Followers Integrity Check? Please be patient - on large systems this could take some time.',
        'submit_modal'  => 'update',
        'modal_width'   => 600,
        'modal_height'  => 400,
        'modal_note'    => 'Please be patient while the Integrity Check runs'
    );
    Core_form_create($_tmp);

    // Validate Follower Counts
    $_tmp = array(
        'name'     => 'validate_counts',
        'label'    => 'validate counts',
        'help'     => 'Check this box so the system will validate and update the number of followers each profile has',
        'type'     => 'checkbox',
        'value'    => 'on',
        'validate' => 'onoff'
    );
    Core_form_field_create($_tmp);
    Core_page_display();
}

//------------------------------
// integrity_check_save
//------------------------------
function view_Follower_integrity_check_save($_post, &$_user, &$_conf)
{
    User_master_only();
    Core_form_validate($_post);
    Core_logger('INF', 'follower integrity check started');
    ini_set('max_execution_time', 82800); // 24 hours max

    // Module install validation
    if (isset($_post['validate_counts']) && $_post['validate_counts'] == 'on') {

        Core_form_modal_notice('update', "validating profile follower counts");

        // Get profiles
        $num = 0;
        $tot = 0;
        while (true) {
            $_sc = array(
                'search'         => array(
                    "_item_id > {$num}"
                ),
                'return_keys'    => array('_profile_id'),
                'skip_triggers'  => true,
                'ignore_pending' => true,
                'privacy_check'  => false,
                'limit'          => 100
            );
            $_rt = Core_db_search_items('Profile', $_sc);
            if ($_rt && is_array($_rt) && is_array($_rt['_items'])) {
                $_dt = array();
                $str = $_rt['_items'][0]['_profile_id'];
                foreach ($_rt['_items'] as $v) {
                    $num = $v['_profile_id'];
                    // Update counts
                    $_sc = array(
                        'search'         => array(
                            "follow_profile_id = {$v['_profile_id']}"
                        ),
                        'return_count'   => true,
                        'skip_triggers'  => true,
                        'ignore_pending' => true,
                        'privacy_check'  => false
                    );
                    $cnt = Core_db_search_items('Follower', $_sc);
                    if (!$cnt || !is_numeric($cnt)) {
                        $cnt = 0;
                    }
                    $_dt["{$v['_profile_id']}"] = array('profile_Follower_item_count' => intval($cnt));
                    $tot++;
                }
                $upd = count($_dt);
                if ($upd > 0) {
                    Core_db_update_multiple_items('Profile', $_dt);
                    Core_form_modal_notice('update', "updated follower counts for {$upd} profiles ({$str} - {$num})");
                }
            }
            else {
                // No more profiles...
                break;
            }
        }
        Core_form_modal_notice('update', "successfully validated profile follower counts for {$tot} profiles");
    }
    Core_form_delete_session();
    Core_logger('INF', 'follower integrity check completed');
    Core_form_modal_notice('complete', 'The follower integrity check options successfully completed');
    exit;
}

//------------------------------
// following
//------------------------------
function view_Follower_following($_post, $_user, $_conf)
{
    // Must be logged
    User_session_require_login();
    User_check_quota_access('Follower');

    // Banner
    Core_page_banner(37);

    // Get all who I'm following
    $_rt = array(
        'search'        => array(
            "_user_id = {$_user['_user_id']}",
            'follow_active = 1'
        ),
        'return_keys'   => array('_item_id', 'follow_profile_id'),
        'order_by'      => array(
            '_item_id' => 'desc',
        ),
        'skip_triggers' => true,
        'limit'         => 10000
    );
    $_rt = Core_db_search_items('Follower', $_rt);
    if ($_rt && is_array($_rt) && isset($_rt['_items'])) {
        $_tm = array();
        foreach ($_rt['_items'] as $rt) {
            $_tm[] = (int) $rt['follow_profile_id'];
        }
        // Get and show all followees
        if (count($_tm) > 0) {
            $_rt  = array(
                'search'    => array(
                    '_profile_id in ' . implode(',', $_tm)
                ),
                'page'      => $_post['p'],
                'pagebreak' => 24
            );
            $_rt  = Core_db_search_items('Profile', $_rt);
            $html = Core_parse_template('following.tpl', $_rt, 'Follower');
            $html .= Core_parse_template('list_pager.tpl', $_rt, 'Core');
            Core_page_custom($html);
        }
    }
    else {
        $_ln = User_load_lang_strings();
     Core_page_note($_ln['Follower'][36]);
    }
    Core_page_display();
}

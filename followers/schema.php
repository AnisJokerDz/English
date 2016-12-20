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
 * @copyright I m dz and i speak english LLC
 */

// make sure we are not being called directly
defined('APP_DIR') or exit();

/**
 * db_schema
 */
function follower
{
    // Datastore (listing)
    Core_db_create_datastore('Follower', 'follow');
    return true;
}

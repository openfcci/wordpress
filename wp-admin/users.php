<?php
require_once('admin.php');
require_once( ABSPATH . WPINC . '/registration-functions.php');

$title = __('Users');
$parent_file = 'profile.php';

$action = $_REQUEST['action'];
$update = '';

switch ($action) {

case 'promote':
	check_admin_referer();

	if (empty($_POST['users'])) {
		header('Location: users.php');
	}

	if ( !current_user_can('edit_users') )
		die(__('You can&#8217;t edit users.'));

 	$userids = $_POST['users'];
	$update = 'promote';
 	foreach($userids as $id) {
		// The new role of the current user must also have edit_users caps
		if($id == $current_user->id && !$wp_roles->role_objects[$_POST['new_role']]->has_cap('edit_users')) {
			$update = 'err_admin_role';
			continue;
		}

 		$user = new WP_User($id);
 		$user->set_role($_POST['new_role']);
 	}

	header('Location: users.php?update=' . $update);

break;

case 'dodelete':

	check_admin_referer();

	if ( empty($_POST['users']) ) {
		header('Location: users.php');
	}

	if ( !current_user_can('edit_users') )
		die(__('You can&#8217;t delete users.'));

	$userids = $_POST['users'];

	$update = 'del';
 	foreach ($userids as $id) {
		if($id == $current_user->id) {
			$update = 'err_admin_del';
			continue;
		}
 		switch($_POST['delete_option']) {
		case 'delete':
			wp_delete_user($id);
			break;
		case 'reassign':
			wp_delete_user($id, $_POST['reassign_user']);
			break;
		}
	}

	header('Location: users.php?update=' . $update);

break;

case 'delete':

	check_admin_referer();

	if (empty($_POST['users'])) {
		header('Location: users.php');
	}

	if ( !current_user_can('edit_users') )
		$error = new WP_Error('edit_users', __('You can&#8217;t delete users.'));

	$userids = $_POST['users'];

	include ('admin-header.php');
?>
<form action="" method="post" name="updateusers" id="updateusers">
<div class="wrap">
<h2><?php _e('Delete Users'); ?></h2>
<p><?php _e('You have specified these users for deletion:'); ?></p>
<ul>
<?php
	$go_delete = false;
 	foreach ($userids as $id) {
 		$user = new WP_User($id);
		if ($id == $current_user->id) {
			echo "<li>" . sprintf(__('ID #%1s: %2s <strong>The current user will not be deleted.</strong>'), $id, $user->user_login) . "</li>\n";
		} else {
			echo "<li><input type=\"hidden\" name=\"users[]\" value=\"{$id}\" />" . sprintf(__('ID #%1s: %2s'), $id, $user->user_login) . "</li>\n";
			$go_delete = true;
		}
 	}
 	$all_logins = $wpdb->get_results("SELECT ID, user_login FROM $wpdb->users ORDER BY user_login");
 	$user_dropdown = '<select name="reassign_user">';
 	foreach ($all_logins as $login) {
		if ( $login->ID == $current_user->id || !in_array($login->ID, $userids) ) {
 			$user_dropdown .= "<option value=\"{$login->ID}\">{$login->user_login}</option>";
 		}
 	}
 	$user_dropdown .= '</select>';
 	?>
 	</ul>
<?php if($go_delete) : ?>
 	<p><?php _e('What should be done with posts and links owned by this user?'); ?></p>
	<ul style="list-style:none;">
		<li><label><input type="radio" id="delete_option0" name="delete_option" value="delete" checked="checked" />
		<?php _e('Delete all posts and links.'); ?></label></li>
		<li><input type="radio" id="delete_option1" name="delete_option" value="reassign" />
		<?php echo '<label for="delete_option1">'.__('Attribute all posts and links to:')."</label> $user_dropdown"; ?></li>
	</ul>
	<input type="hidden" name="action" value="dodelete" />
	<p class="submit"><input type="submit" name="submit" value="<?php _e('Confirm Deletion'); ?>" /></p>
<?php else : ?>
	<p><?php _e('There are no valid users selected for deletion.'); ?></p>
<?php endif; ?>
</div>
</form>
<?php

break;

case 'adduser':
	check_admin_referer();

	$user_id = add_user();
	if ( is_wp_error( $user_id ) )
		$errors = $user_id;
	else {
		header('Location: users.php?update=add');
		die();
	}

default:

	$list_js = true;
	$users_js = true;

	include ('admin-header.php');

	$userids = $wpdb->get_col("SELECT ID FROM $wpdb->users;");

	foreach($userids as $userid) {
		$tmp_user = new WP_User($userid);
		$roles = $tmp_user->roles;
		$role = array_shift($roles);
		$roleclasses[$role][$tmp_user->user_login] = $tmp_user;
	}

	?>

	<?php 
	if (isset($_GET['update'])) : 
		switch($_GET['update']) {
		case 'del':
		?>
			<div id="message" class="updated fade"><p><?php _e('User deleted.'); ?></p></div>
		<?php
			break;
		case 'add':
		?>
			<div id="message" class="updated fade"><p><?php _e('New user created.'); ?></p></div>
		<?php
			break;
		case 'promote':
		?>
			<div id="message" class="updated fade"><p><?php _e('Changed roles.'); ?></p></div>
		<?php
			break;
		case 'err_admin_role':
		?>
			<div id="message" class="error"><p><?php _e("The current user's role must have user editing capabilities."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other user roles have been changed.'); ?></p></div>
		<?php
			break;
		case 'err_admin_del':
		?>
			<div id="message" class="error"><p><?php _e("You can't delete the current user."); ?></p></div>
			<div id="message" class="updated fade"><p><?php _e('Other users have been deleted.'); ?></p></div>
		<?php
			break;
		}
	endif; 
	if ( is_wp_error( $errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
		foreach( $errors->get_error_codes() as $code)
			foreach( $errors->get_error_messages($code) as $message )
				 echo "<li>$message</li>";
		?>
		</ul>
	</div>
	<?php 
	endif;
	?>

<form action="" method="post" name="updateusers" id="updateusers">
<div class="wrap">
	<h2><?php _e('User List by Role'); ?></h2>
  <table cellpadding="3" cellspacing="3" width="100%">
	<?php
	foreach($roleclasses as $role => $roleclass) {
		ksort($roleclass);
		?>

	<tr>
		<th colspan="8" align="left"><h3><?php echo $wp_roles->role_names[$role]; ?></h3></th>
	</tr>
	<tr>
		<th><?php _e('ID') ?></th>
		<th><?php _e('Username') ?></th>
		<th><?php _e('Name') ?></th>
		<th><?php _e('E-mail') ?></th>
		<th><?php _e('Website') ?></th>
		<th><?php _e('Posts') ?></th>
		<th>&nbsp;</th>
	</tr>
	<tbody id="role-<?php echo $role; ?>"><?php
	$style = '';
	foreach ($roleclass as $user_object) {
		$style = (' class="alternate"' == $style) ? '' : ' class="alternate"';
		echo "\n\t" . user_row( $user_object, $style );
	}

	?>

	</tbody>
<?php
	}
?>
  </table>


	<h2><?php _e('Update Users'); ?></h2>
  <ul style="list-style:none;">
  	<li><input type="radio" name="action" id="action0" value="delete" /> <label for="action0"><?php _e('Delete checked users.'); ?></label></li>
  	<li>
		<input type="radio" name="action" id="action1" value="promote" /> <label for="action1"><?php _e('Set the Role of checked users to:'); ?></label>
		<select name="new_role"><?php wp_dropdown_roles(); ?></select>
	</li>
  </ul>
	<p class="submit"><input type="submit" value="<?php _e('Update &raquo;'); ?>" /></p>
</div>
</form>

<div class="wrap">
<h2><?php _e('Add New User') ?></h2>
<?php echo '<p>'.sprintf(__('Users can <a href="%1$s">register themselves</a> or you can manually create users here.'), get_settings('siteurl').'/wp-register.php').'</p>'; ?>
<form action="" method="post" name="adduser" id="adduser">
  <table class="editform" width="100%" cellspacing="2" cellpadding="5">
    <tr>
      <th scope="row" width="33%"><?php _e('Nickname') ?>
      <input name="action" type="hidden" id="action" value="adduser" /></th>
      <td width="66%"><input name="user_login" type="text" id="user_login" value="<?php echo $new_user_login; ?>" /></td>
    </tr>
    <tr>
      <th scope="row"><?php _e('First Name') ?> </th>
      <td><input name="first_name" type="text" id="first_name" value="<?php echo $new_user_firstname; ?>" /></td>
    </tr>
    <tr>
      <th scope="row"><?php _e('Last Name') ?> </th>
      <td><input name="last_name" type="text" id="last_name" value="<?php echo $new_user_lastname; ?>" /></td>
    </tr>
    <tr>
      <th scope="row"><?php _e('E-mail') ?></th>
      <td><input name="email" type="text" id="email" value="<?php echo $new_user_email; ?>" /></td>
    </tr>
    <tr>
      <th scope="row"><?php _e('Website') ?></th>
      <td><input name="url" type="text" id="url" value="<?php echo $new_user_uri; ?>" /></td>
    </tr>
<?php
$show_password_fields = apply_filters('show_password_fields', true);
if ( $show_password_fields ) :
?>
    <tr>
      <th scope="row"><?php _e('Password (twice)') ?> </th>
      <td><input name="pass1" type="password" id="pass1" />
      <br />
      <input name="pass2" type="password" id="pass2" /></td>
    </tr>
<?php endif; ?>
    <tr>
      <th scope="row"><?php _e('Role'); ?></th>
      <td><select name="role" id="role"><?php wp_dropdown_roles( get_settings('default_role') ); ?></select></td>
    </tr>
  </table>
  <p class="submit">
    <input name="adduser" type="submit" id="addusersub" value="<?php _e('Add User &raquo;') ?>" />
  </p>
  </form>
<div id="ajax-response"></div>
</div>
	<?php

break;
}

include('admin-footer.php');
?>

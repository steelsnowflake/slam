<form name='viewPreferences' action='' method='POST'>
	<input type='button' value='Preferences' onClick="showPopupDiv('pub/user_prefs.php','userDiv',{'noclose':true})"/>
</form>
<form name='changePassword' action='' method='POST'>
	<input type='button' value='Change Password' onClick="showPopupDiv('pub/password_change.php','userDiv',{'noclose':true})"/>
</form>
<form name='logoutForm' action='index.php' method='POST'>
	<input type='hidden' name='logout' value='true' />
	<input type='submit' value='Log Out' />
</form>
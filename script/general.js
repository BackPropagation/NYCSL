function login(user) {
	$("#loginNav").css("display", "none");
	$("#logoutNav").css("display", "inline");

	$("#submitForm").append("<input type='hidden' name='userID' value='"+user.userID+"'>");

	$('#submitButton').click(function() {
    	$('#myFile').click();
	})
	$('#logoutButton').click(function() {
    	destroySession(false);
    	logOut();
	})
}

function logOut() {
	$("#loginNav").css("display", "inline");
	$("#logoutNav").css("display", "none");

	$("#loginButton").click(function() {
		var email = $("#login_user").val();
		var password = $("#login_pass").val();

		// Does not exist. LOG IN FAIL
		if(getUser(null, email, password) == null) {
			console.log("log in fail");
		} else {
			storeUserSession(null, email, password, false);
			console.log(getSession());
			login(getSession());
		}
	})
	$("#registerButton").click(function() {
		var email = $("#register_user").val();
		var password = $("#register_pass").val();
		var firstName = $("#register_first").val();
		var lastName = $("#register_last").val();
		var schoolName = $("#register_school").val();

		storeUserBackend(email, password, firstName, lastName, schoolName, false);
		storeUserSession(null, email, password, false);
		login(getSession());
	})
}

$(document).ready(function() {
	var user = getSession();
	console.log(user);

	// not logged in
	if(user == null) {
		logOut();
	} else {
		login(user)
	}
})
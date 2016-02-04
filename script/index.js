$(function() {
	$('.dropdown-toggle').dropdown();
	$('.dropdown input, .dropdown label').click(function(e) {
		e.stopPropagation();
	});

	var table = {
		init: function(submissions) {
			this.cacheDOM();
			this.bindEvents();
			this.setSubmissions(submissions);
		},
		cacheDOM: function() {
			this.$table = $("#leaderTable")
		},
		bindEvents: function() {
			$(document).on("click", ".matchDrop", this.toggleDropdown.bind(this));
		},
		setSubmissions: function(submissions) {
			this.submissions = submissions;
			this.render();
		},
		render: function() {
			this.$table.empty();
			for(var a = 0; a < this.submissions.length; a++) {
				var user = this.submissions[a].user;
				var score = this.submissions[a].score;
				this.$table.append("<tbody id='user" + user.userID + "'><tr><th scope='row'>"+(a+1)+"</th><td><a href='student.php?userID="+user.userID+"'>"+user.firstName+" "+user.lastName+"</a></td><td><a href='school.php?schoolName="+user.schoolName+"'>"+user.schoolName+"</a></td><td><a class='matchDrop' userID= '"+user.userID+"' href='#'>"+score+"</a></td></tr></tbody>");
			}
		},
		toggleDropdown: function(event) {
			var user = this.getUserWithID($(event.toElement).attr("userID"));
			var latestGames = getLatestGamesForUser(user.userID);
			console.log(latestGames)
			for(var a = 0; a < latestGames.length; a++) {
				var opponent = latestGames[a].users[0].userID == user.userID ? this.getUserWithID(latestGames[a].users[1].userID) : this.getUserWithID(latestGames[a].users[0].userID);
				var opponentRank = latestGames[a].users[0].userID == user.userID ? latestGames[a].users[1].rank : latestGames[a].users[0].rank;
				var gameResult = opponentRank === "0" ? "Lost" : "Won";
				console.log(gameResult)
				$("#user"+user.userID).append("<tr class='gameRow'><td></td><td>vs <a href='student.php?userID="+opponent.userID+"'>"+opponent.firstName+" "+opponent.lastName+"</a></td><td><a href='school.php?schoolName="+opponent.schoolName+"'>"+opponent.schoolName+"</a></td><td><a href='#gameID="+latestGames[a].gameID+"' onclick='modalLinkClicked(\""+latestGames[a].replayFilename+"\")'>"+gameResult+"</a></td></tr>");
			}

			var display = $(event.toElement).parent().parent().parent().find(".gameRow").css("display");
			if (display === "none") $(event.toElement).parent().parent().parent().find(".gameRow").css("display","table-row")
			else $(event.toElement).parent().parent().parent().find(".gameRow").css("display","none")
		},
		getUserWithID: function(userID) {
			for(var a = 0; a < this.submissions.length; a++) if(this.submissions[a].user.userID == userID) return this.submissions[a].user;
			return getUser(userID);
		}
	};

	var problemDisplay = {
		init: function(problem) {
			this.problem = problem;
			this.cacheDOM();
			this.render();
		},
		cacheDOM: function() {
			this.$header = $("#jHeader");
			this.$paragraph = $("#jParagraph");
			this.$rulesPanel = $("#rulesPanelBody");

			this.table = table;
			this.table.init(this.problem.submissions);
		},
		setProblem: function(problem) {
			this.problem = problem;
			this.table.setSubmissions(problem.submissions);
			this.render();
		},
		render: function() {
			this.$header.html(this.problem.problemFullName);
			this.$paragraph.html(this.problem.problemDescription);
			
			var result = $.ajax({
				url: "problems/descriptions/"+this.problem.problemName+".html", 
				async: true,
				method: "GET",
				context: this,
				success: function(result) {
					this.$rulesPanel.html(result);
				}
		    });
		},
	};

	var problemPanner = {
		init: function(startingIndex) {
			this.problemIndex = startingIndex;
			this.problemSize = getProblemsSize();

			this.cacheDOM();
			this.bindEvents();
		},
		cacheDOM: function() {
			this.$backButton = $("#backButton");
			this.$forwardButton = $("#nextButton");
			this.$archivedTag = $("archivedTag");

			this.problemDisplay = problemDisplay;
			this.problemDisplay.init(getProblemWithIndex(this.problemIndex));

			if(this.problemIndex == 0) {
				this.$forwardButton.css("visibility", "hidden");
				this.$archivedTag.css("display", "none");
			}
			if(this.problemIndex == this.problemSize-1) {
				this.$backButton.css("visibility", "visible");
			}
		},
		bindEvents: function() {
			this.$backButton.click(this, this.moveBack.bind(this));
			this.$forwardButton.click(this, this.moveForward.bind(this));
		},
		render: function() {
			this.problemDisplay.setProblem(getProblemWithIndex(this.problemIndex));
		},
		moveBack: function() {
			this.problemIndex++;
			if(this.problemIndex == this.problemSize-1) {
				this.$backButton.css("visibility", "hidden");
			}
			if(this.problemIndex == 1) {
				this.$forwardButton.css("visibility", "visible");
				this.$archivedTag.css("display", "block");
			}

			this.render();
		},
		moveForward: function() {
			this.problemIndex--;
			if(this.problemIndex == 0) {
				this.$forwardButton.css("visibility", "hidden");
				this.$archivedTag.css("display", "none");
			}
			if(this.problemIndex == this.problemSize-2) {
				this.$backButton.css("visibility", "visible");
			}

			this.render();
		}
	};

	// Get problem index from GET parameter
	var index = parseInt(getGET("problemIndex"));
	if(isNaN(index) == true || index == null || index === "" || index === " ") {
		index = parseInt(getGET("problemID"));
		if(isNaN(index) == true || index == null || index === "" || index === " ") {
			index = 0;
		} else {
			index = problemIDToIndex(index);
		}
	}
	problemPanner.init(index);

	if(getGET("didVerify") != null) verifySuccess()
	if(getGET("didNotVerify") != null) verifyError()
	if(getGET("didRecover") != null) recoverPasswordSuccess()
	if(getGET("didNotRecover") != null) recoverPasswordError()

	renderMathInElement(document.getElementById("rulesPanelBody"));
});

function modalLinkClicked(gameFile) {
	console.log(gameFile)
	$('#gameModal').modal('show');
	getGameFile(gameFile, function(data) {
		console.log(data)
		begin(data)
	})
}

function verifySuccess() {
	$("#messageBox").empty()
	$("#messageBox").append($("<div class='alert alert-success alert-dismissible' role='alert'><button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button><strong>Email Verification Success.</strong>&nbsp;&nbsp;Your email has been verified. You may now log in.</div>"))
}

function verifyError() {
	$("#messageBox").empty()
	$("#messageBox").append($("<div class='alert alert-danger alert-dismissible' role='alert'><button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button><strong>Email Verification Failed.</strong>&nbsp;&nbsp;There was a problem verifying your email.</div>"))
}

function recoverPasswordSuccess() {
	$("#messageBox").empty()
	$("#messageBox").append($("<div class='alert alert-info alert-dismissible' role='alert'><button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button><strong>Password Changed.</strong>&nbsp;&nbsp;You successfully changed your password!</div>"))
}

function recoverPasswordError() {
	$("#messageBox").empty()
	$("#messageBox").append($("<div class='alert alert-danger alert-dismissible' role='alert'><button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button><strong>Password Change Failed.</strong>&nbsp;&nbsp;There was a problem changing your password. If this issue is persistent, email <a href='mailto:contact@nycsl.io'>contact@nycsl.io</a>.</div>"))
}
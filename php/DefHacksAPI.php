<?php

require_once 'API.class.php';
class DefHacksAPI extends API
{

	// The database
	private $mysqli = NULL;

	public function __construct($request, $origin) {
		$this->initDB();
		parent::__construct($request);
	}

	// Initializes and returns a mysqli object that represents our mysql database
	private function initDB() {
		$this->mysqli = new mysqli("DefHacks.db.12061709.hostedresource.com", 
			"DefHacks", 
			"***REMOVED***", 
			"DefHacks");
		
		if (mysqli_connect_errno()) { 
			echo "<br><br>There seems to be a problem with our database. Reload the page or try again later.";
			exit(); 
		}
	}

	private function select($sql) {
		$res = mysqli_query($this->mysqli, $sql);
		if($res) return mysqli_fetch_array($res, MYSQLI_ASSOC);
		else return NULL;
	}

	private function selectMultiple($sql) {
		$res = mysqli_query($this->mysqli, $sql);
		$finalArray = array();

		while($temp = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
			array_push($finalArray, $temp);
		}

		return $finalArray;
	}

	private function insert($sql) {
		mysqli_query($this->mysqli, $sql);
	}

	// API ENDPOINTS
	protected function schools() {
		if($this->method == 'GET') {
			$schoolNames = array();
			$users = $this->selectMultiple("SELECT * FROM User");
			foreach($users as $user) {
				$alreadyIn = false;
				foreach($schoolNames as $name) {
					if($name === $user['schoolName']) {
						$alreadyIn = true;
						break;
					} 
				}
				if($alreadyIn == false) {
					array_push($schoolNames, $user['schoolName']);
				}
			}
			return $schoolNames;
		}
	}

	protected function session() {
		session_start();
		if($this->method == 'GET') {
			if(count($_SESSION) > 0) return $_SESSION;
			else return NULL;
		} else if(isset($_POST['email']) & isset($_POST['password'])) {
			$email= $_POST['email'];
			$password = $_POST['password'];
			$userArray = $this->select("SELECT * FROM User WHERE email = '$email' AND password = '$password'");
			$_SESSION = $userArray;
		} else if(isset($_POST['userID']) & isset($_POST['password'])) {
			$userID= $_POST['userID'];
			$password = $_POST['password'];
			$userArray = $this->select("SELECT * FROM User WHERE userID = '$userID' AND password = '$password'");
			$_SESSION = $userArray;
		} else if($this->method == 'DELETE') {
			session_destroy();
		} else {
			return NULL;
		}
	}

	protected function user() {
		if(isset($_GET['userID']) && isset($_GET['password'])) {
			$userID = $_GET['userID'];
			$password = $_GET['password'];
			return $this->select("SELECT * FROM User WHERE userID = $userID and password = '$password'");
		} else if(isset($_GET['email']) && isset($_GET['password'])) {
			$email = $_GET['email'];
			$password = $_GET['password'];
			return $this->select("SELECT * FROM User WHERE email = '$email' and password = '$password'");
		} else if(isset($_GET['schoolName'])) {
			$schoolName = $_GET['schoolName'];
			return $this->selectMultiple("SELECT * FROM User WHERE schoolName = '$schoolName'");
		} else if(isset($_GET['userID'])) {
			$userID = $_GET['userID'];
			return $this->select("SELECT * FROM User WHERE userID = $userID");
		} else if(
			isset($_POST['email']) && 
			isset($_POST['password']) &&
			isset($_POST['firstName']) &&
			isset($_POST['lastName']) &&
			isset($_POST['schoolName'])) {

			$email = $_POST['email'];
			$password = $_POST['password'];
			$firstName = $_POST['firstName'];
			$lastName = $_POST['lastName'];
			$schoolName = $_POST['schoolName'];

			$this->insert("INSERT INTO User (email, password, firstName, lastName, schoolName) VALUES ('$email', '$password', '$firstName', '$lastName', '$schoolName')");

		} else {
			return "Didnt reach an endpoint";
		}
		return "Success";
	}

	protected function problem() {
		if(isset($_GET['problemID'])) {
			$problemID = $_GET['problemID'];
			return $this->select("SELECT * FROM Problem WHERE problemID = $problemID");
		}
		if(isset($_GET['index'])) {
			$index = $_GET['index'];
			$problems = $this->selectMultiple("SELECT * FROM Problem");
			return $problems[count($problems) - (1+$index)];
		}
		if(isset($_GET['size'])) {
			$problems = $this->selectMultiple("SELECT problemID FROM Problem");
			return count($problems);
		} 
	}

	protected function submission() {
		if(isset($_GET['userID'])) {
			$userID = $_GET['userID'];
			return $this->selectMultiple("SELECT * FROM Submission WHERE userID = $userID");
		} else if(isset($_GET['submissionID'])) {
			$submissionID = $_GET['submissionID'];
			return $this->select("SELECT * FROM Submission WHERE submissionID = $submissionID");
		} else if(isset($_GET['problemID']) && isset($_GET['schoolName'])) {
			$problemID = $_GET['problemID'];
			$schoolName = $_GET['schoolName'];

			$submissions = array();
			$possibleSubmissions = $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID");
			foreach($possibleSubmissions as $possibleSubmission) {
				$userID = $possibleSubmission['userID'];
				$userArray = $this->select("SELECT userID FROM User WHERE schoolName = '$schoolName' and userID = $userID");
				if(count($userArray) > 0) {
					array_push($submissions, $possibleSubmission);
				}
			}
			return $submissions;
		} else if(isset($_GET['problemID'])) {
			$problemID = $_GET['problemID'];
			return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID");
		} else if($this->method === 'GET') {

			$problemArrayArray = $this->selectMultiple("SELECT * FROM Problem");
			$problemArray = $problemArrayArray[count($problemArrayArray)-1];

			$problemID = $problemArray['problemID'];

			if($problemArray['isAscending'] == 0) return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score DESC");
			else return $this->selectMultiple("SELECT * FROM Submission WHERE problemID = $problemID ORDER BY score ASC");
		} else if(
			isset($_POST['userID']) &&
			isset($_FILES['outputFile']['name'])) {

			// Parameters
			$userID = $_POST['userID'];
			//$outputFile = $this->mysqli->real_escape_string(file_get_contents($_FILES['outputFile']["tmp_name"]));

			// Last one is current one
			$problemArrayArray = $this->selectMultiple("SELECT * FROM Problem");
			$problemArray = $problemArrayArray[count($problemArrayArray)-1];
			$problemID = $problemArray['problemID'];
			$problemName = $problemArray['problemName'];

			$targetPath = "../problems/outputs/$problemName/";
			$ext = explode('.', basename( $_FILES['outputFile']['name']));
			$targetPath = $targetPath . md5(uniqid()) . "." . $ext[count($ext)-1];
			move_uploaded_file($_FILES['outputFile']['tmp_name'], $targetPath);

			// Pass target file to python script
			exec("python ../problems/scripts/$problemName.py $targetPath", $pythonOutput);
			$score = intval($pythonOutput[0]);

			$this->insert("INSERT INTO Submission (problemID, userID, score) VALUES ($problemID, $userID, $score)");
		} else {
			return "Didn't reach endpoint";
		}
		return "Success";
	}
 }

 ?>
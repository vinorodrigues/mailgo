<!DOCTYPE html>
<html lang="en">
<head>
	<title>MailGo</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta charset="utf-8">
	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.2/summernote.css">
	<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.2/css/bootstrap-select.min.css">
	<link rel="stylesheet" href="//cdn.rawgit.com/flatlogic/awesome-bootstrap-checkbox/master/awesome-bootstrap-checkbox.css">
	<!-- Styles -->
	<style>
		.tab-content {
			padding-top: 1.5em;
		}
		.button-band {
			padding-top: 0.5em;
			padding-bottom: 0.5em;
			background-color: rgba(127,127,127,0.1);
		}
	</style>
</head>
<body>
<?php

define('SETTINGS_FILE', 'mailgo.config.json');
define('HISTORY_FILE', 'mailgo.history.json');

/**
 * Usefull debugging
 */
function var_dump_ret($mixed = null) {
	ob_start();
	var_dump($mixed);
	$content = ob_get_clean();
	return htmlspecialchars($content, ENT_QUOTES);
}

/**
 * More usefull debugging
 */
function var_dump_pre($mixed = null, $name = false) {
	echo '<pre>';
	if ($name !== false)
		echo '<span style="color:#F00">' . $name . '</span><span style="color:#0F0"> = </span>';
	echo '<span style="color:#00F">' . var_dump_ret($mixed) . '</span>';
	echo '</pre>';
}

/**
 * Test arrays
 */
function array_equal($a, $b) {
	if (is_array($a) && is_array($b) && (count($a) == count($b))) {
		array_multisort($a);
		array_multisort($b);
		return ( serialize($a) === serialize($b) );
	} else
		return false;
}

/**
 * Merge settings array with default set
 */
function merge_defaults($set, $def, $clean = false) {
	foreach ($def as $k => $v)
		if ((!array_key_exists($k, $set)) && empty($set[$k]) )
			$set[$k] = $v;

	if ($clean)
		foreach ($set as $k => $v)
			if (!array_key_exists($k, $def)) unset($set[$k]);

	return $set;
}

/**
 */
function alert_close() {
	return '<button type="button" class="close fade in" data-dismiss="alert" aria-label="Close">' .
		'<span aria-hidden="true">&times;</span>' .
		'</button>';
}

$settings_list = array(
	'debug' => 'bool',
	'lang' => 'text',
	'bcc' => 'email',
	'send-with' => array(
		'php' => 'Native PHP mail() function',
		'phpmailer' => 'PHPMailer/PHPMailer class',
		),
	'smtp-host' => 'text',
	'port' => 'number',
	'auth' => 'bool',
	'username' => 'text',
	'password' => 'password',
	);

$settings_defaults = array(
	'debug' => false,
	'lang' => 'en-US',
	'bcc' => '',
	'send-with' => 'php',
	'smtp-host' => 'localhost',
	'port' => 25,
	'auth' => false,
	'username' => '',
	'password' => '',
	);

$message_defaults = array(
	'fr_name' => '',
	'fr_email' => '',
	'to_name_1' => '',
	'to_email_1' => '',
	'priority' => 0,
	'subject' => '',
	'message' => '',
	);

/*
 * ////////////////////////////////////////
 * Action starts here
 * ////////////////////////////////////////
 */

$action = isset($_REQUEST['a']) ? strtolower($_REQUEST['a']) : false;

/* Read settings and history */

if (file_exists(SETTINGS_FILE)) {
	$json = file_get_contents(SETTINGS_FILE);
	$settings = json_decode($json, true);
} else
	$settings = array();
$settings = merge_defaults($settings, $settings_defaults);

if (file_exists(HISTORY_FILE)) {
	$json = file_get_contents(HISTORY_FILE);
	$history = json_decode($json, true);
	$history = merge_defaults($history, $message_defaults);
} else
	$history = null;


/* Set up notification arrays */

$err = $inf = $suc = array();

/*
 * ////////////////////////////////////////
 * Perform actions
 * ////////////////////////////////////////
 */

if ($action !== false) {
	switch ($action) {
		/*
		 * ////////////////////////////////////////
		 * Send Button pressed
		 * ////////////////////////////////////////
		 */
		case 'send' :
			$post = merge_defaults($_POST, $message_defaults, true);
			$sending = array(
				'fr_name' => strip_tags($post['fr_name']),
				'fr_email' => strip_tags($post['fr_email']),
				'to_name_1' => strip_tags($post['to_name_1']),
				'to_email_1' => strip_tags($post['to_email_1']),
				'priority' => intval($post['priority']),
				'subject' => strip_tags($post['subject']),
				'message' => $post['message'],
				);

			switch ($settings['send-with']) {
				/*
				 * ////////////////////////////////////////
				 * Send via PHP mail() function
				 * ////////////////////////////////////////
				 */
				case 'php':
					// $err[] = 'No implemented yet';
					// break;

				/*
				 * ////////////////////////////////////////
				 * Send via PHPMailer/PHPMailer class
				 * ////////////////////////////////////////
				 */
				case 'phpmailer':
					require 'phpmailer/class.phpmailer.php';
					require 'phpmailer/class.smtp.php';

					$mail = new PHPMailer;
					if ('php' == $settings['send-with'])
						$mail->isSendmail();
					else
						$mail->isSMTP();
					// $mail->SMTPDebug = 0;
					$mail->Debugoutput = 'html';
					$mail->XMailer = 'MailGo';
					if ($sending['priority'] > 0)
						$mail->Priority = $sending['priority'];

					$mail->Host = $settings['smtp-host'];
					$mail->Port = $settings['port'];
					if ($settings['auth']) {
						$mail->SMTPAuth = true;
						$mail->Username = $settings['username'];
						$mail->Password = $settings['password'];
					} else
						$mail->SMTPAuth = false;

					$mail->setFrom($sending['fr_email'], $sending['fr_name']);
					$mail->addAddress($sending['to_email_1'], $sending['to_name_1']);
					if (!empty($settings['bcc']))
						$mail->addBCC($settings['bcc']);
					$mail->addCustomHeader('Language', $settings['lang']);
					$mail->addCustomHeader('Content-Language', $settings['lang']);
					$mail->addCustomHeader('Accept-Language', $settings['lang']);
					$mail->Subject = $sending['subject'];
					$mail->msgHTML($sending['message']);
					if (!$mail->send()) {
						$err[] = "Mailer Error: " . $mail->ErrorInfo;
					} else {
						$suc[] = "Message sent!";
					}
					break;
			}

			if (!array_equal($history, $sending)) {

				$fp = fopen(HISTORY_FILE, 'w');
				if (!$fp) {
					$err[] = 'Cannot open <code>'.HISTORY_FILE.'</code> file';
				} else {
					fwrite($fp, json_encode($sending));
					fclose($fp);
				}

				$history = $sending;
			}

			break;

		case 'save' :
			$new_settings = array();

			foreach ($settings_list as $key => $type) {
				if (is_array($type)) {
					$new_settings[$key] = strip_tags($_POST[$key]);
				} elseif (array_key_exists($key, $_POST)) {
					switch ($type) {
						case 'text' :
						case 'password' :
						case 'url' :
						case 'email' :
							$new_settings[$key] = strip_tags($_POST[$key]);
							break;
						case 'bool' :
							$new_settings[$key] = filter_var(strip_tags($_POST[$key]), FILTER_VALIDATE_BOOLEAN);
							break;
						case 'number' :
							$new_settings[$key] = intval(strip_tags($_POST[$key]));
							break;
					}
				} else {
					switch ($type) {
						case 'bool' :
							$new_settings[$key] = false;

					}
				}
			}

			$save = !array_equal($settings, $new_settings);

			if ($save) {
				$fp = fopen(SETTINGS_FILE, 'w');
				if (!$fp) {
					$err[] = 'Cannot open <code>'.SETTINGS_FILE.'</code> file';
				} else {
					fwrite($fp, json_encode($new_settings));
					fclose($fp);

					$suc[] = 'Settings saved';
					$settings = $new_settings;
				}
			} else {
				$inf[] = 'Nothing to save';
			}

			break;

		/*
		 * Action was present, but unknown
		 */
		default :
			$err[] = 'Unknown action';
	}
}

/*
 * ////////////////////////////////////////
 * HTML starts here
 * ////////////////////////////////////////
 */
?>

<div class="container"><div class="row"><div class="col-sm-10 col-sm-offset-1">
	<div class="not-a-page-header">
		<h1><span class="fa-stack"><i class="fa fa-square fa-stack-2x"></i><i class="fa fa-paper-plane-o fa-stack-1x fa-inverse"></i></span>
		<a href="">MailGo</a>
		<small><i>by</i> Vino Rodrigues</small>
		</h1>
	</div>

	<?php
	/*
	 * Notifications
	 */
	if (count($err) > 0)
		foreach ($err as $msg)
			echo '<div class="alert alert-danger">' .
				'<i class="fa fa-exclamation-triangle"></i> ' .
				$msg . alert_close() . '</div>';
	if (count($inf) > 0)
		foreach ($inf as $msg)
			echo '<div class="alert alert-info">' .
				'<i class="fa fa-info-circle"></i> ' .
				$msg . alert_close() . '</div>';
	if (count($suc) > 0)
		foreach ($suc as $msg)
			echo '<div class="alert alert-success">' .
				'<i class="fa fa-check"></i> ' .
				$msg . alert_close() . '</div>';
	?>

	<ul class="nav nav-tabs" role="tablist">
		<li class="active"><a role="tab" href="#send_pane" data-toggle="tab">Send Mail</a></li>
		<li><a role="tab" href="#settings_pane" data-toggle="tab">Settings</a></li>
		<?php if ($settings['debug']) { ?>
		<li><a role="tab" href="#debug_pane" data-toggle="tab">Debug</a></li>
		<?php } ?>
	</ul>

	<div class="tab-content">
		<div role="tabpanel" class="tab-pane fade in active" id="send_pane">

			<?php /*
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 * Sending pane
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 */ ?>

			<form action="" class="form-horizontal" method="post">
				<div class="container-fluid">
					<div class="form-group row">
						<label for="fr_name"
							class="col-xs-2 control-label">From</label>
						<div class="col-xs-10 col-sm-5">
							<input type="text"
								class="form-control"
								id="fr_name"
								name="fr_name"
								placeholder="Name">
						</div>
						<div class="col-xs-10 col-xs-offset-2 col-sm-5 col-sm-offset-0">
							<input type="email"
								class="form-control"
								id="fr_email"
								name="fr_email"
								placeholder="Email"
								required>
						</div>
					</div>
					<div class="form-group row">
						<label for="to_name"
							class="col-xs-2 control-label">To</label>
						<div class="col-xs-10 col-sm-5">
							<input type="text"
								class="form-control"
								id="to_name_1"
								name="to_name_1"
								placeholder="Name">
						</div>
						<div class="col-xs-10 col-xs-offset-2 col-sm-5 col-sm-offset-0">
							<input type="text"
								class="form-control"
								id="to_email_1"
								name="to_email_1"
								placeholder="Email"
								required>
						</div>
					</div>
					<div class="form-group row">
						<label for="priority"
							class="col-xs-2 control-label">Priority</label>
						<div class="col-xs-10">
							<select class="selectpicker form-control-inline"
								id="priority"
								name="priority">
								<option value="0"></option>
								<option value="1">High</option>
								<option value="3">Normal</option>
								<option value="5">Low</option>
							</select>
						</div>
					</div>
					<div class="form-group row">
						<label for="subject"
							class="col-xs-2 control-label">Subject</label>
						<div class="col-xs-10">
							<input type="text"
								class="form-control"
								id="subject"
								name="subject"
								placeholder="Subject">
						</div>
					</div>
					<div class="form-group row">
						<label for="message"
							class="col-xs-2 control-label">Message</label>
						<div class="col-xs-10">
							<textarea class="form-control"
								id="message"
								name="message"
								rows="10"></textarea>
						</div>
					</div>
					<div class="form-group row button-band">
						<div class="col-xs-offset-2 col-xs-10">
							<input type="hidden"
								name="a"
								value="send">
							<button id="btnSend"
								type="submit"
								class="btn btn-primary">
								<i class="fa fa-paper-plane"></i>
								Send
							</button>
							<button id="btnReset"
								type="reset"
								class="btn btn-danger">
								<i class="fa fa-eraser"></i>
								Reset
							</button>
						</div>
					</div>
				</div>
			</form>

		</div><div role="tabpanel" class="tab-pane fade" id="settings_pane">

			<?php /*
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 * Settings pane
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 */ ?>

			<form action="" class="form-horizontal" method="post">
				<div class="container-fluid">
			<?php
				foreach ($settings_list as $key => $type) {
					echo '<div class="form-group row">';
					echo '<label for="'.$key.'" class="col-xs-2 control-label">'.ucwords($key).'</label>';
					echo '<div class="col-xs-10">';
					if (is_array($type)) {
						echo '<select class="form-control-inline selectpicker" id="'.$key.'" name="'.$key.'">';
						foreach ($type as $k => $v) {
							echo '<option value="' . $k . '"';
							if (array_key_exists($key, $settings) && ($settings[$key] == $k)) echo ' selected="selected"';
							echo '>' . $v . '</option>';
						}
						echo '</select>';
					} else
						switch ($type) {
							case 'text':
							case 'password':
							case 'number':
							case 'url':
							case 'email':
								echo '<input type="'.$type.'" class="form-control" id="'.$key.'" name="'.$key.'" placeholder="'.ucwords($key).'"';
								if (array_key_exists($key, $settings)) echo ' value="'.$settings[$key].'"';
								echo ' />';
								if ('password' == $type)
									echo '<small class="text-muted help-block">Passwords are <u><b><i>not</i></b></u> encrypted in settings file.</small>';
								break;
							case 'bool':
								if (array_key_exists($key, $settings)) {
									$checked = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
								} else
									$checkd = false;
								echo '<div class="checkbox">';
								echo '<input type="checkbox" id="'.$key.'" name="'.$key.'"';
								if ($checked) echo ' checked="checked"';
								echo ' value="1" />';
								echo '<label for="'.$key.' class="text-muted">'.ucwords($key).'</label></div>';
								break;
						}
					echo '</div></div>';
				}

			?>
					<div class="form-group row button-band">
						<div class="col-xs-offset-2 col-xs-10">
						<input type="hidden"
								name="a"
								value="save">
							<button id="btnSave"
								type="submit"
								class="btn btn-warning">
								<i class="fa fa-save"></i>
								Save
								</button>
						</div>
					</div>
				</div>
			</form>

		<?php if ($settings['debug']) { ?>
		</div><div role="tabpanel" class="tab-pane fade" id="debug_pane">

			<div class="container-fluid"><div class="row">
			<?php
			/*
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 * Debug pane
			 * ////////////////////////////////////////
			 * ////////////////////////////////////////
			 */

			echo '<div class="col-sm-6 col-md-4">';
			var_dump_pre( $settings, '$settings' );
			echo '</div><div class="col-sm-6 col-md-4">';
			var_dump_pre( $history, '$history' );
			echo '</div><div class="col-sm-6 col-md-4">';
			if (isset($sending)) var_dump_pre( $sending, '$sending' );
			echo '</div>';
			?>
			</div></div>

		<?php } ?>

		</div>
	</div>
</div></div></div>

<?php /*
/* ////////////////////////////////////////
 * End of it all
 * ////////////////////////////////////////
 */ ?>
<div style="display:none">
	<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
	<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/summernote/0.8.2/summernote.js"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.2/js/bootstrap-select.min.js"></script>

	<script type="text/javascript">
		$(document).ready(function() {
			$("#message").summernote({
				height: 200,
				lang: "<?= $settings['lang'] ?>",
				placeholder: "write here..."
			});

			$(".selectpicker").selectpicker({
				noneSelectedText: ""
			});
			$(".selectpicker").selectpicker("refresh");

			$("#btnReset").click( function(){
				$("#message").summernote("reset");
			})

			<?php if (is_array($history)) { ?>
			$("#fr_name").val("<?= $history['fr_name'] ?>");
			$("#fr_email").val("<?= $history['fr_email'] ?>");
			$("#to_name_1").val("<?= $history['to_name_1'] ?>");
			$("#to_email_1").val("<?= $history['to_email_1'] ?>");
			$("#priority").val("<?= $history['priority'] ?>");
			$("#priority").selectpicker("val", <?= $history['priority'] ?>);
			$("#subject").val("<?= $history['subject'] ?>");
			$("#message").summernote("code", <?= json_encode($history['message']) ?>);
			<?php } ?>

		});
	</script>
</div>
</body>
</html>

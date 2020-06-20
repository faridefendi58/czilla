<?php

$app->post('/contact-us', function ($request, $response, $args) {
    $message = 'Failed in sending your message.';
    $success = true;
    $settings = $this->get('settings');
    if (isset($_POST['Contact'])){
        $model = new \ExtensionsModel\ContactModel();
        $model->name = $_POST['Contact']['name'];
        $model->email = $_POST['Contact']['email'];
        $model->phone = $_POST['Contact']['phone'];
        $model->message = $_POST['Contact']['message'];
        $model->created_at = date("Y-m-d H:i:s");
        $save = \ExtensionsModel\ContactModel::model()->save($model);
        if ($save) {
            //send mail to admin
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                //Server settings
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host = $settings['params']['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $settings['params']['admin_email'];
                $mail->Password = $settings['params']['smtp_secret'];
                $mail->SMTPSecure = $settings['params']['smtp_secure'];
                $mail->Port = $settings['params']['smtp_port'];
    
                //Recipients
                $mail->setFrom( $settings['params']['admin_email'], 'Admin Chordzilla' );
                $mail->addAddress( $settings['params']['admin_email'], 'Farid Efendi' );
                $mail->addReplyTo( $_POST['Contact']['email'], $_POST['Contact']['name'] );
    
                //Content
                $mail->isHTML(true);
                $mail->Subject = '[ChordZilla] Kontak Kami';
                $mail->Body = "Halo Admin, 
    	        <br/><br/>
                Ada pesan baru dari pengunjung dengan data berikut:
                <br/><br/>
                <b>Judul pesan</b> : ".$_POST['Contact']['subject']." <br/>
                <b>Nama pengunjung</b> : ".$_POST['Contact']['name']." <br/> 
                <b>Alamat Email</b> : ".$_POST['Contact']['email']." <br/>
                <br/>
                <b>Isi Pesan</b> :<br/> ".$_POST['Contact']['message']."";
    
                $mail->send();
            } catch (Exception $e) {}
            $success = true;
            $message = 'Your message was sent successfully. We will immediately respond to your message.';
        } else {
            $success = false;
        }
    }

    return $this->view->render($response, 'contact-us.phtml', [
        'success' => $success,
        'message' => $message
    ]);
});

foreach (glob(__DIR__.'/*_controller.php') as $controller) {
	$cname = basename($controller, '.php');
	if (!empty($cname)) {
		require_once $controller;
	}
}

$app->group('/contact', function () use ($user) {
    $this->group('/messages', function() use ($user) {
        new Extensions\Controllers\MessagesController($this, $user);
    });
});

?>

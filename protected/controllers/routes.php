<?php
// Modules Routes
foreach(glob($settings['settings']['basePath'] . '/modules/*/controllers/routes.php') as $mod_routes) {
    require_once $mod_routes;
}

// Extensions routes
foreach(glob($settings['settings']['basePath'] . '/extensions/*/controllers/routes.php') as $ext_routes) {
    require_once $ext_routes;
}

$app->get('/sitemap.xml', function ($request, $response, $args) {
    $tools = new \Components\Tool();

    $this->view->render($response, 'sitemap.xml', [
        'results' => $tools->get_sitemaps()
    ]);

    return $response->withHeader('Content-Type','text/xml');
});

$app->get('/[{name}]', function ($request, $response, $args) {
    
	if (empty($args['name']))
		$args['name'] = 'index';

    $settings = $this->get('settings');
    $exts = json_decode( $settings['params']['extensions'], true );

    if (!file_exists($settings['theme']['path'].'/'.$settings['theme']['name'].'/views/'.$args['name'].'.phtml')) {
        $msong = null;
        if (in_array( 'song', $exts )) {
            $msong = new \ExtensionsModel\SongModel();
        }

        $shorten_file = $settings['basePath'].'/data/songs/shortens/'.$args['name'].'.json';
        if (file_exists($shorten_file)) {
            $shorten_content = file_get_contents($shorten_file);
            if (!empty($shorten_content)) {
                $shorten_content = json_decode($shorten_content, true);
                if (array_key_exists('shorten_link', $shorten_content) && $shorten_content['shorten_link'] > 0) {
                    $data = $shorten_content;
                    $msong = new \ExtensionsModel\SongModel();
                    // save the counter
                    $crmodel = new \ExtensionsModel\SongCordRefferenceModel();
                    $record = $crmodel->recordVisit($data['title'], $data['id']);

                    return $this->view->render($response, 'song_chord.phtml', [
                        'data' => $data,
                        'msong' => $msong
                    ]);
                }
            }
        }

        return $this->view->render($response, '404.phtml', [
            'msong' => $msong
        ]);
        /*return $this->response
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Page not found!');*/
    }

    $mpost = null;
    if (in_array( 'blog', $exts )) {
        $mpost = new \ExtensionsModel\PostModel();
    }

    $msong = null;
    if (in_array( 'song', $exts )) {
        $msong = new \ExtensionsModel\SongModel();
    }

    return $this->view->render($response, $args['name'] . '.phtml', [
        'name' => $args['name'],
        'request' => $request->getParams(),
        'mpost' => $mpost,
        'msong' => $msong
    ]);
});

/*$app->post('/contact-us', function ($request, $response, $args) {
    $message = 'Failed in sending your message.';
    $settings = $this->get('settings');
    if (isset($_POST['Contact'])){
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
        } catch (Exception $e) {
            echo 'Message could not be sent.';
            echo 'Mailer Error: ' . $mail->ErrorInfo;
            exit;
        }

        $message = 'Your message was sent successfully. We will immediately respond to your message.';
    }

    echo $message; exit;
});*/

$app->post('/tracking', function ($request, $response, $args) {
    if (isset($_POST['s'])){
        $model = new \Model\VisitorModel('create');
        $model->client_id = 0;
        if(!empty($_POST['s'])){
            $model->session_id = $model->getCookie('_ma',false);
            if (!empty($model->cookie)){
                $model->date_expired = $model->cookie;
            } else {
                //Yii::app()->request->cookies->remove('_ma');
                $model->date_expired = date("Y-m-d H:i:s",time()+1800);
            }
        }
        $model->ip_address = $_SERVER['REMOTE_ADDR'];
        $model->page_title = $_POST['t'];
        $model->url = $_POST['u'];
        $model->url_referrer = $_POST['r'];
        $model->created_at = date('Y-m-d H:i:s');
        $model->platform = $_POST['p'];
        $model->user_agent = $_POST['b'];

        require_once $this->settings['basePath'] . '/components/mobile_detect.php';
        $mobile_detect = new \Components\MobileDetect();
        $model->mobile = ($mobile_detect->isMobile())? 1 : 0;

        $create = \Model\VisitorModel::model()->save(@$model);

        if ($create > 0) {
            if ($model->session_id == 'false' || empty($model->session_id)) {
                $model2 = \Model\VisitorModel::model()->findByPk($model->id);
                $model2->session_id = md5($create);
                $update = \Model\VisitorModel::model()->update(@$model2);
                //$cookie_time = (3600 * 0.5); // 30 minute
                //setcookie("ma_session", $model->session_id, time() + $cookie_time, '/');
            }
            //set notaktif
            $model->deactivate($model->session_id);
            // update the current record
            if (!is_object($model2))
                $model2 = \Model\VisitorModel::model()->findByPk($model->id);
            $model2->active = 1;
            $update2 = \Model\VisitorModel::model()->update($model2);

            echo $model2->session_id;
        }else{
            echo 'failed';
        }

        exit;
    }
});

$app->post('/chord-request', function ($request, $response, $args) {
    $result = ['success' => 0, 'message' => 'Your request failed to send.'];
    $settings = $this->get('settings');
    if (isset($_POST['Request'])){
        if (empty($_POST['Request']['name'])
            && empty($_POST['Request']['email'])
            && empty($_POST['Request']['title'])
            && empty($_POST['Request']['artist'])) {
            $result['message'] = "Name, email, song title, and band name required";
        }
        if (!filter_var($_POST['Request']['email'], FILTER_VALIDATE_EMAIL)) {
            $result['message'] = $_POST['Request']['email']." not valid email";
        }
        //send mail to admin
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // save to db
            $model = new \ExtensionsModel\SongRequestModel('create');
            $model->name = $_POST['Request']['name'];
            $model->email = $_POST['Request']['email'];
            $model->song_title = $_POST['Request']['title'];
            $model->song_artist = $_POST['Request']['artist'];
            if (isset($_POST['Request']['type'])) {
                $model->type = $_POST['Request']['type'];
            }
            if (isset($_POST['Request']['notes'])) {
                $model->notes = $_POST['Request']['notes'];
            }
            $model->created_at = date("Y-m-d H:i:s");
            $save = \ExtensionsModel\SongRequestModel::model()->save(@$model);

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
            $mail->setFrom( $settings['params']['admin_email'], 'Admin '.$settings['params']['site_name'] );
            $mail->addAddress( $settings['params']['admin_email'], 'Farid Efendi' );
            $mail->addReplyTo( $_POST['Request']['email'], $_POST['Request']['name'] );

            //Content
            $mail->isHTML(true);
            $mail->Subject = '['.$settings['params']['site_name'].'] Request '.ucfirst($_POST['Request']['type']);
            $mail->Body = "Halo Admin, 
	        <br/><br/>
            Ada request ".ucfirst($_POST['Request']['type'])." baru dari pengunjung dengan data berikut:
            <br/><br/>
            <b>Judul Lagu</b> : ".$_POST['Request']['title']." <br/>
            <b>Nama Penyanyi</b> : ".$_POST['Request']['artist']." <br/> 
            <b>Direquest oleh</b> : ".$_POST['Request']['name']." - ".$_POST['Request']['email'];

            $mail->send();
        } catch (Exception $e) {
            $result['message'] = 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
        }

        $result['success'] = 1;
        $result['message'] = 'Your request was sent successfully. We will immediately follow up on your request.';
    }

    return $response->withJson($result);
});

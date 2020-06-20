<?php
// also just redirection detected error by google
$app->get('/artist/[{name}]', function ($request, $response, $args) {
    return $response->withStatus(301)->withHeader('Location', '/');
});
// frontend url
$app->get('/lyric/search', function ($request, $response, $args) use ($app) {
    $model = new \ExtensionsModel\SongModel();
    $params = $request->getParams();
    if ($params['type'] == 'chord' || $params['type'] == 'chord') {
        return $response->withRedirect('/search?q='.$params['q']);
    }

    return $this->view->render($response, 'song_search.phtml', [
        'model' => $model,
        'params' => $params,
    ]);
});

$app->get('/chord/search', function ($request, $response, $args) use ($app) {
    $params = $request->getParams();
    return $response->withRedirect('/search?q='.$params['q']);
});

$app->get('/lirik[/{artist}[/{title}]]', function ($request, $response, $args) {
    if (empty($args['title'])) {
        return $response->withRedirect('/lyric/'. $args['artist']);
    } else {
        return $response->withRedirect('/lyric/'. $args['artist'] .'/'. $args['title']);
    }

    return $response->withRedirect('/lyric/'. $args['title']);
});

$app->get('/search', function ($request, $response, $args) {
    $model = new \ExtensionsModel\SongModel();
    $params = $request->getParams();

    // track search keyword
    if (!empty($params['q'])) {
        $type = 'chord';
        if (isset($params['type'])) {
            $type = $params['type'];
        }
        $smodel = \ExtensionsModel\SongSearchModel::model()->findByAttributes(['keywords' => strtolower($params['q']), 'type' => $type]);
        if (!$smodel instanceof \RedBeanPHP\OODBBean) {
            $smodel = new \ExtensionsModel\SongSearchModel();
            $smodel->keywords = strtolower($params['q']);
            $smodel->type = $type;
            $smodel->frequency = 1;
            $smodel->created_at = date('c');
            $smodel->updated_at = date('c');
            $save = \ExtensionsModel\SongSearchModel::model()->save($smodel);
        } else {
            if ($smodel->status == 0) {
                $smodel->frequency = $smodel->frequency + 1;
                $smodel->updated_at = date('c');
                $update = \ExtensionsModel\SongSearchModel::model()->update($smodel);
            }
        }
    }

    return $this->view->render($response, 'chord_search.phtml', [
        'model' => $model,
        'params' => $params,
    ]);
});

$app->get('/chord[/{artist}[/{title}]]', function ($request, $response, $args) {
    $theme = $this->settings['theme'];
    $model = new \ExtensionsModel\SongModel();

    if (empty($args['title'])) {
        // it should be the title = artist slug if the length > 0
        if (strlen($args['artist']) > 1){
            $amodel = \ExtensionsModel\SongArtistModel::model()->findByAttributes(['slug' => $args['artist']]);
            $songs = null;
            if ($amodel instanceof \RedBeanPHP\OODBBean) {
                $songs = $model->getSongs(['artist_id' => $amodel->id, 'type'=>'chord']);
            }

            return $this->view->render($response, 'song_chord_index.phtml', [
                'model' => $model,
                'songs' => $songs,
                'amodel' => $amodel
            ]);
        } else { //if the title is Abjad
            $abmodel = \ExtensionsModel\SongAbjadModel::model()->findByAttributes(['title' => strtoupper($args['artist'])]);
            $artists = null;
            if ($abmodel instanceof \RedBeanPHP\OODBBean) {
                $artists = $model->getArtists(['abjad_id' => $abmodel->id, 'has_chord' => true]);
            }

            return $this->view->render($response, 'song_chord_index.phtml', [
                'model' => $model,
                'selected_abjad' => strtoupper($args['artist']),
                'artists' => $artists
            ]);
        }
    } else {
        $use_cached = false;
        if (array_key_exists('artist', $args)
            && array_key_exists('use_cached_file', $this->settings['params'])
            && $this->settings['params']['use_cached_file']) {

            $dir = 'protected/data/songs/';
            $file = $dir. $args['artist'].'_'.$args['title'].'.json';
            if(!file_exists($file)) {
                $data = $model->getSong($args['title']);
                if (!empty($data['chord_permalink']) && $data['chord_permalink'] == $args['title']) {
                    $new_file = fopen($file, "w");
                    file_put_contents($file, json_encode($data));
                    $use_cached = true;
                }
            } else {
                $data = file_get_contents($file);
                if (empty($data)) {
                    $data = $model->getSong($args['title']);
                    file_put_contents($file, json_encode($data));
                    $use_cached = true;
                } else {
                    $data = json_decode($data, true);
                    if (!empty($data['chord_permalink']) && $data['chord_permalink'] == $args['title']) {
                        $use_cached = true;
                    } else {
                        unlink($file);
                    }
                }
            }
        }

        if (!$use_cached) {
            $data = $model->getSong($args['title']);
        }

        if (empty($data)) {
            // check by title
            $data = $model->getSongByTitle(strtolower($args['title']), $args['artist']);

            if (!empty($data['chord_permalink']))
                return $response->withRedirect('/chord/'.$args['artist'].'/'.$data['chord_permalink']);
            else
                return $response->withRedirect('/chord/'.$args['artist'].'/'.$data['chord_slug']);
        } else {
            if (!empty($data['chord_permalink']) && $data['chord_permalink'] != $args['title']) {
                return $response->withRedirect('/chord/'.$args['artist'].'/'.$data['chord_permalink']);
            }
        }

        // save the counter
        $crmodel = new \ExtensionsModel\SongCordRefferenceModel();
        $record = $crmodel->recordVisit($args['title'], $data['id']);

        return $this->view->render($response, 'song_chord.phtml', [
            'data' => $data,
            'msong' => $model
        ]);
    }

    return $this->view->render($response, '404.phtml');
});

$app->get('/genre/[{genre_slug}]', function ($request, $response, $args) {
    $theme = $this->settings['theme'];
    $model = \ExtensionsModel\SongGenreModel::model()->findByAttributes(['slug' => $args['genre_slug']]);

    if ($model instanceof \RedBeanPHP\OODBBean) {
        $smodel = new \ExtensionsModel\SongModel();
        $data = $smodel->getArtists(['genre_id' => $model->id, 'has_chord' => 1]);

        return $this->view->render($response, 'genre_artist.phtml', [
            'data' => $data,
            'model' => $model,
            'msong' => $smodel
        ]);
    }

    return $this->view->render($response, '404.phtml');
});

$app->get('/lyric[/{artist}[/{title}]]', function ($request, $response, $args) {
    $theme = $this->settings['theme'];
    $model = new \ExtensionsModel\SongModel();

    if (empty($args['title'])) {
        // it should be the title = artist slug if the length > 0
        if (strlen($args['artist']) > 1){
            $amodel = \ExtensionsModel\SongArtistModel::model()->findByAttributes(['slug' => $args['artist']]);
            $songs = null;
            if ($amodel instanceof \RedBeanPHP\OODBBean) {
                $songs = $model->getSongs(['artist_id' => $amodel->id, 'type'=>'chord']);
            }

            return $this->view->render($response, 'song_lyric_index.phtml', [
                'model' => $model,
                'songs' => $songs,
                'amodel' => $amodel
            ]);
        } else { //if the title is Abjad
            $abmodel = \ExtensionsModel\SongAbjadModel::model()->findByAttributes(['title' => strtoupper($args['artist'])]);
            $artists = null;
            if ($abmodel instanceof \RedBeanPHP\OODBBean) {
                $artists = $model->getArtists(['abjad_id' => $abmodel->id, 'has_chord' => true]);
            }

            return $this->view->render($response, 'song_lyric_index.phtml', [
                'model' => $model,
                'selected_abjad' => strtoupper($args['artist']),
                'artists' => $artists
            ]);
        }
    } else {
        $perm = $model->getSongChordPremalink($args['title']);
        $use_cached = false;
        if (array_key_exists('artist', $args)
            && array_key_exists('use_cached_file', $this->settings['params'])
            && $this->settings['params']['use_cached_file']) {

            $dir = 'protected/data/songs/';
            $file = $dir. $args['artist'].'_'.$perm.'.json';
            if(!file_exists($file)) {
                $data = $model->getSong($perm);
                if (!empty($data['chord_permalink']) && $data['chord_permalink'] == $perm) {
                    $new_file = fopen($file, "w");
                    file_put_contents($file, json_encode($data));
                    $use_cached = true;
                }
            } else {
                $data = file_get_contents($file);
                if (empty($data)) {
                    $data = $model->getSong($perm);
                    file_put_contents($file, json_encode($data));
                    $use_cached = true;
                } else {
                    $data = json_decode($data, true);
                    if (!empty($data['chord_permalink']) && $data['chord_permalink'] == $perm) {
                        $use_cached = true;
                    } else {
                        unlink($file);
                    }
                }
            }
        }

        if (!$use_cached) {
            $data = $model->getSong($perm);
        }

        $chord_reformated = $model->reformatChordContent($data['chord']);
        $data['lyric'] = $model->getCleanLyrics($chord_reformated);
        $data['lyric_meta_decription'] = substr(strip_tags($data['lyric']), 0, 100);
        // save the counter
        //$crmodel = new \ExtensionsModel\SongCordRefferenceModel();
        //$record = $crmodel->recordVisit($args['title'], $data['id']);

        return $this->view->render($response, 'song_lyric.phtml', [
            'data' => $data,
            'msong' => $model
        ]);
    }

    return $this->view->render($response, '404.phtml');
});

foreach (glob(__DIR__.'/*_controller.php') as $controller) {
	$cname = basename($controller, '.php');
	if (!empty($cname)) {
		require_once $controller;
	}
}

foreach (glob(__DIR__.'/../components/*.php') as $component) {
    $cname = basename($component, '.php');
    if (!empty($cname)) {
        require_once $component;
    }
}

$app->group('/song', function () use ($user) {
    $this->group('/summary', function() use ($user) {
        new Extensions\Controllers\SummaryController($this, $user);
    });
    $this->group('/lyrics', function() use ($user) {
        new Extensions\Controllers\LyricsController($this, $user);
    });
    $this->group('/artists', function() use ($user) {
        new Extensions\Controllers\ArtistController($this, $user);
    });
    $this->group('/chords', function() use ($user) {
        new Extensions\Controllers\ChordsController($this, $user);
    });
    $this->group('/requests', function() use ($user) {
        new Extensions\Controllers\RequestsController($this, $user);
    });
    $this->group('/genres', function() use ($user) {
        new Extensions\Controllers\GenresController($this, $user);
    });
	$this->group('/images', function() use ($user) {
        new Extensions\Controllers\ImagesController($this, $user);
    });
});

?>

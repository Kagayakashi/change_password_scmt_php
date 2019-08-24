<?php
// ---------------------------------------------------------------------------->
// Copyright Simourg 2019-*. All rights reserved.
// Author: Vyacheslav Odinokov
// ---------------------------------------------------------------------------->

// Подключение конфигурационного файла
require_once __DIR__ . '/../i_custom.cfg';

// Логин
// $lgn = 'sim.test';
// Текущий пароль
// $pwd = 'zYzx3zxxzxx';
// Новый пароль
// $pwd_new = 'zYzx3zxНzxР';
// Идентификатор пользователя в системе
// $uid = 1230;

$lgn = '';
$pwd = '';
$pwd_new = '';
$uid = 0;

// Запуск процедуры
session_start();
scmt_login( $lgn, $pwd );
scmt_pwd_change( $uid, $pwd, $pwd_new );
scmt_logout( $uid );
session_destroy();

function scmt_login( $lgn, $pwd )
{
	// Авторизироваться в систему за пользователя
    $scmt_body  = [
        'login'     => $lgn,
        'auth_type' => 1,
        'auth_data' => ['pwd' => $pwd],
        'user_env'  => ['ip' => $_SERVER['REMOTE_ADDR']],
    ];

    $scm = scmt_structure( 9010, $scmt_body );
	$response = send( $scm );

    return $response;
}

function scmt_pwd_change( $uid, $pwd, $pwd_new )
{
	// Изменить пароль пользователю
	$scmt_body  = [
        'uid'         => $uid,
        'action'      => 1,
        'pwd'         => $pwd_new,
        'pwd_current' => $pwd,
		'check_only'  => 0

    ];
    $scm = scmt_structure( 9016, $scmt_body );
	$response = send( $scm );

	// Если ошибок нет (1), вернуть сообщение "пароль обновлён".
	// При ошибки, вернуть сообщение об ошибке
	// Сообщения возвращает система
	$error = pwd_error( $response );

	if( $error === 1 ){ echo 'Пароль успешно обновлён!'; }
	else			  { echo $error; }


    return $response;
}

function scmt_logout( $uid )
{
	// Выйти пользователем из системы
    $scmt_body  = [
        'uid' => $uid,
    ];
    $scm = scmt_structure( 9012, $scmt_body );
	$response = send( $scm );

    return $response;
}

function scmt_structure( $scm_type, $scmt_body )
{
	// Сформировать header и body для отправки SCMT
	$iid = hexdec( I_CFG['sys_iid'] );
	$cid = hexdec( I_CFG['sys_cid'] );

	if( $scm_type == 9012 || $scm_type == 9016 ) { $uid = $scmt_body['uid']; }
	else{ $uid = 0; }

    $scm = [
        'header' => [
            'scm_ver'       => 1,
            'scm_sender'    => $iid,
            'scm_recipient' => $cid,
            'scm_type'      => $scm_type,
            'scm_type_ver'  => 1,
            'scm_stm'       => 0,
            'scm_error'     => 0,
            'scm_created'   => 0,
            'scm_expires'   => time() + 5,
            'scm_uid'       => $uid,
            'scm_sid'       => session_id(),
            'scm_crc'       => '',
        ],
        'body'   => $scmt_body,
    ];

	// Запустить создание контрольной суммы массива
	$scm = md_summ($scm);

    return $scm;
}

function md_summ($scm)
{
	// Сохранить контрольную сумму в header->scm_crc
    $scm['header']['scm_crc'] = '';
	$res = md5(md_summ_array($scm));
    $scm['header']['scm_crc'] = $res;

    return $scm;
}

function md_summ_array( $pieces )
{
	// Сделать контрольную сумму массива
    $res  = '';
    $keys = array_keys($pieces);

    $ci = count($keys);
    for ( $i = 0; $i < $ci; $i++ )
	{
        if (is_array( $pieces[$keys[$i]] ) )
		{
            $buf = $pieces[$keys[$i]];
            ksort( $buf );

            $res .= md_summ_array( $buf );
        }
		else
		{
            $res .= $pieces[$keys[$i]];
        }
    }

    return $res;
}

function send( $scm )
{
	$body = serialize( $scm );

	$url = I_CFG['sys_url'];
	// Заголовок
	$header = array
	(
		"Content-type: multipart/form-data",
		"Content-length: ".strlen( $body ),
	);

	// Отправка запроса через Curl
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
	curl_setopt( $ch, CURLOPT_POST, true );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
	$result = curl_exec( $ch );
	// Проверка отправленного запроса на ошибку
	if( curl_errno( $ch ) )
	{
		// Если произошла ошибка, вернуть её номер
		$error = curl_errno( $ch );
		curl_close( $ch );

		echo 'Код ошибки: '.$error;
	}
	else
	{
		// Если ошибки нет, вернуть ответ на запрос
		curl_close( $ch );
	}

	return $result;
}

function pwd_error( $response )
{
	// Проверка на наличие ошибки в header и body
	// Возврат текст ошибки, либо 1 - ошибок нет
	$res = unserialize( $response );

	$body   = $res['body'];
	$header = $res['header'];

	if( $header['scm_error'] > 0 )
	{
		$body = $res['body'];
		if( array_key_exists( 'err_msg', $body ) )
		{
			return $body['err_msg'];
		}

		if( array_key_exists( 'pwd', $body ) )
		{
			return $body['pwd'];
		}
	}
	else
	{
		return 1;
	}
}

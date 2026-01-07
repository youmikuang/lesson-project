<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
    public function jsonResponse($errCode = 0, $errMessage = '', $data = [])
    {
        $response = [
            'ret' => $errCode,
            'msg' => is_string($errMessage) ? $errMessage : json_encode($errMessage),
            'data' => $data,
        ];

        return response()->json($response);
    }
}

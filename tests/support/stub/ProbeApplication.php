<?php

declare(strict_types=1);

namespace yii2\extensions\roadrunner\tests\support\stub;

use yii\web\IdentityInterface;
use yii2\extensions\psrbridge\http\{Application, Response, UploadedFile};

/**
 * Returns request state through the real bridge lifecycle without routing dependencies.
 *
 * @extends Application<IdentityInterface>
 */
final class ProbeApplication extends Application
{
    public function handleRequest($request): \yii\web\Response
    {
        $response = $this->response;

        $response->format = Response::FORMAT_JSON;
        $response->data = [
            'sessionId' => $this->session->getId(),
            'upload' => UploadedFile::getInstanceByName('file')?->name,
        ];

        return $response;
    }
}

<?php

namespace Imanghafoori\TwoFactorAuth\Http\Controllers;

use Illuminate\Routing\Controller;
use Imanghafoori\TwoFactorAuth\Facades\AuthFacade;
use Imanghafoori\TwoFactorAuth\Http\ResponderFacade;
use Illuminate\Support\Facades\Validator;
use Imanghafoori\TwoFactorAuth\Facades\TokenStoreFacade;
use Imanghafoori\TwoFactorAuth\Facades\TokenSenderFacade;
use Imanghafoori\TwoFactorAuth\Facades\UserProviderFacade;
use Imanghafoori\TwoFactorAuth\Facades\TokenGeneratorFacade;

class TokenSenderController extends Controller
{
    public function loginWithToken()
    {
        $token = request('token');
        $uid = TokenStoreFacade::getUidByToken($token)->getOrSend(
            [ResponderFacade::class, 'tokenNotFound']
        );

        AuthFacade::loginById($uid);

        return ResponderFacade::loggedIn();
    }

    public function issueToken()
    {
        $email = request('email');

        $this->validateEmailIsValid();
        $this->checkUserIsGuest();
        // throttle the route

        // find user row in DB or Fail
        $user = UserProviderFacade::getUserByEmail($email)->getOrSend(
            [ResponderFacade::class, 'userNotFound']
        );

        // 1. stop block users
        if (UserProviderFacade::isBanned($user->id)) {
            return ResponderFacade::blockedUser();
        }

        // 2. generate token
        $token = TokenGeneratorFacade::generateToken();
        // 3. save token
        TokenStoreFacade::saveToken($token, $user->id);
        // 4. send token
        TokenSenderFacade::send($token, $user);
        // 5. send Response
        return ResponderFacade::tokenSent();
    }

    private function validateEmailIsValid()
    {
        $v = Validator::make(request()->all(), ['email' => 'email|required']);
        if ($v->fails()) {
            ResponderFacade::emailNotValid()->throwResponse();
        }
    }

    private function checkUserIsGuest()
    {
        if (AuthFacade::check()) {
            ResponderFacade::youShouldBeGuest()->throwResponse();
        }
    }
}





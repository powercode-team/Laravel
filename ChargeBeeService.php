<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ChargeBeeService
{
    /**
     * Webhook gate method
     * Connect to DB and call action
     *
     * @param $request
     * @return bool
     */
    public static function webhook($request)
    {
        $company = Company::where('customer_id', $request['content']['subscription']['customer_id'])->first();

        if($company){
            if($request['event_type'] == "subscription_changed"){
                $result = self::subscriptionChanged($company, $request);
            }
            if($request['event_type'] == "subscription_deleted"){
                $result = self::subscriptionDelete($company);
            }
            if($request['event_type'] == "subscription_cancellation_scheduled"){
                $result = self::subscriptionCancellationScheduled($company);
            }
            if($request['event_type'] == "subscription_cancellation_reminder"){
                $result = self::subscriptionCancellationReminder($company);
            }
            if($request['event_type'] == "subscription_cancelled"){
                $result = self::subscriptionCancelled($company);
            }
            if($request['event_type'] == "subscription_reactivated"){
                $result = self::subscriptionReactivated($company);
            }
            if($request['event_type'] == "subscription_paused"){
                $result = self::subscriptionPaused($company);
            }
            if($request['event_type'] == "subscription_resumed"){
                $result = self::subscriptionResumed($company);
            }
            if($request['event_type'] == "payment_failed"){
                $result = self::paymentFailed($company);
            }

            //Log::info('payment_succeeded: '.$request['content']['transaction']['customer_id']);
            return $result;

        } else {
            return false;
        }
    }

    /**
     * mark in DB that user subscription is change
     */
    public static function subscriptionChanged($company, $request)
    {
        $company->update([
            'plan_id'               => $request['content']['subscription']['plan_id'],
            'subscription_id'       => $request['content']['subscription']['id'],
            'subscription_exp'      => Carbon::now()->timestamp($request['content']['subscription']['current_term_end']),
            'subscription_status'   => 'active',
        ]);

        return true;
    }

    /**
     * mark in DB that user subscription is delete
     */
    public static function subscriptionDelete($company)
    {
        $users = User::where('company_id', $company['id'])->get();

        if($users->isNotEmpty()){
            foreach ($users as $user){
                Notification::create([
                    'user_id'       => $user->id,
                    'type'          => 2,
                    'description'   => 'Your subscription has been delete',
                ]);
            }
        }

        $company->update([
            'subscription_id'       => null,
            'plan_id'               => null,
            'subscription_exp'      => null,
            'subscription_status'   => null
        ]);

        return true;
    }

    /**
     * mark in DB that user subscription is cancelled
     */
    public static function subscriptionCancelled($company)
    {
        $users = User::where('company_id', $company['id'])->get();

        if($users->isNotEmpty()){
            foreach ($users as $user){
                Notification::create([
                    'user_id'       => $user->id,
                    'type'          => 3,
                    'description'   => 'Your subscription has been cancelled',
                ]);
            }
        }

        $company->update([
            'subscription_status' => 'cancelled'
        ]);

        return true;
    }

    /**
     * mark in DB that user subscription is reactivated
     */
    public static function subscriptionReactivated($company)
    {
        $company->update([
            'subscription_status' => 'active'
        ]);

        return true;
    }

    /**
     * mark in DB that user subscription is paused
     */
    public static function subscriptionPaused($company)
    {
        $users = User::where('company_id', $company->id)->get();

        if($users->isNotEmpty()){
            foreach ($users as $user){
                Notification::create([
                    'user_id'       => $user->id,
                    'type'          => 4,
                    'description'   => 'Your subscription has been paused',
                ]);
            }
        }

        $company->update([
            'subscription_status' => 'paused'
        ]);

        return true;
    }

    /**
     * mark in DB that user subscription is resumed from pause
     */
    public static function subscriptionResumed($company)
    {
        $company->update([
            'subscription_status'   => 'active'
        ]);

        return true;
    }

    public static function subscriptionCancellationScheduled($company)
    {
        $company->update([
            'subscription_status'   => 'non_renewing'
        ]);

        return true;
    }

    /**
     * make notification before 3 days after subscription expired
     */
    public static function subscriptionCancellationReminder($company)
    {
        $users = User::where('company_id', $company->id)->get();
        if($users->isNotEmpty()){
            foreach ($users as $user){
                Notification::create([
                    'user_id'       => $user->id,
                    'type'          => 5,
                    'description'   => 'Your subscription expires in 6 days',
                ]);
            }
        }

        return true;
    }

    /**
     * make notification if payment failed
     */
    public static function paymentFailed($company)
    {
        $users = User::where('company_id', $company->id)->get();
        if($users->isNotEmpty()){
            foreach ($users as $user){
                Notification::create([
                    'user_id'       => $user->id,
                    'type'          => 6,
                    'description'   => 'Payment failure',
                ]);
            }
        }

        $company->update([
            'subscription_status'   => 'payment_failure'
        ]);

        return true;
    }

}
<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GSuiteAccountsRepository
{
    /**
     * GSuite Directory Accounts Client
     * \Google_Service_Directory_Resource_Users
     */
    protected $accounts_client;

    /**
     * GSuite account user name max length
     */
    protected static $maxNameLength = 60;

    /**
     * Boostrap the Directory Service
     * @return Wyattcast44\GSuite\GSuiteAccountsRepository
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/
     */
    public function __construct(GSuiteDirectory $directory_client)
    {
        $this->setAccountsClient($directory_client);

        return $this;
    }

    /**
     * Set the GSuite Directory Account client
     * @return Wyattcast44\GSuite\GSuiteAccountsRepository
     */
    protected function setAccountsClient(GSuiteDirectory $directory_client)
    {
        $this->accounts_client = $directory_client->users;

        return $this;
    }

    /**
     * Delete a GSuite Account
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/users/delete
     */
    public function delete(string $email)
    {
        try {
            $status = $this->accounts_client->delete($email);
        } catch (\Exception $e) {
            throw new Exception("Error deleting account with email: {$email}.", 1);
        }

        return $status;
    }

    /**
     * Fetch a GSuite account by email
     * @return \Google_Service_Directory_User
     */
    public function get(string $email)
    {
        try {
            $account = $this->accounts_client->get($email, ['projection' => 'full']);
        } catch (\Exception $e) {
            throw new Exception("Error retriving account with email: {$email}", 1);
        }

        return $account;
    }

    /**
     * Create a new GSuite account
     * https://developers.google.com/admin-sdk/directory/v1/reference/users/insert
     */
    public function insert(array $name, string $email, string $password = '')
    {
        /**
         * Ensure $name has proper keys
         */
        if (!Arr::has($name, ['first_name', 'last_name'])) {
            throw new \Exception("Name parameter must contain the following items: first_name, last_name", 1);
        }

        /**
         * Ensure names meet max length requirement
         */
        if (strlen($name['first_name']) > self::$maxNameLength || strlen($name['last_name']) > self::$maxNameLength) {
            throw new \Exception("First and last name must be 60 characters or less", 1);
        }

        /**
         * Check email availability
         */
        if (!$this->checkEmailAvailability($email)) {
            throw new Exception("Email address already taken.", 1);
        }

        /**
         * If no password is supplied, create random password
         */
        if ($password === '') {
            $password = Str::random(16);
        }

        /**
         * Create and set the Google Directory Name
         */
        $directory_name = tap(new \Google_Service_Directory_UserName, function ($directory_name) use ($name) {
            $directory_name->setGivenName(ucfirst($name['first_name']));
            $directory_name->setFamilyName(ucfirst($name['last_name']));
        });

        /**
         * Create and configure the new Google User
         */
        $google_user = tap(new \GoogleServiceUser, function ($google_user) use ($directory_name, $email, $password) {
            $google_user->setName($directory_name);
            $google_user->setPrimaryEmail($email);
            $google_user->setPassword($password);
            $google_user->setChangePasswordAtNextLogin(true);
        });

        /**
         * Attempt to actually create the new GSuite account
         */
        try {
            $account = $this->accounts_client->insert($google_user);
        } catch (\Exception $e) {
            throw new \Exception("Error Processing Request", 1);
        }

        return $account;
    }

    /**
     * Get GSuite accounts in your domain
     */
    public function list()
    {
        $accounts = $this->accounts_client->listUsers(['domain' => config('gsuite.domain')])->users;

        return $accounts;
    }

    public function makeAdmin(string $email)
    {
        //
    }

    public function update(string $email)
    {
        //
    }

    /**
     * @link https://developers.google.com/admin-sdk/directory/v1/guides/search-users
     */
    public function search()
    {
        //
    }

    public function checkEmailAvailability(string $email)
    {
        return true;
    }

    public function flushCache()
    {
        Cache::forget(config('gsuite.cache.accounts.key'));
    }

    public function shouldCache()
    {
        return config('gsuite.cache.accounts.should-cache');
    }
}

<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace controllers\publics;

/**
 * Page des users.
 */
class User extends \descartes\Controller
{
    private $internal_user;

    /**
     * Cette fonction est appelée avant toute les autres :
     * Elle vérifie que l'utilisateur est bien connecté.
     *
     * @return void;
     */
    public function __construct()
    {
        $bdd = \descartes\Model::_connect(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD);
        $this->internal_user = new \controllers\internals\User($bdd);

        \controllers\internals\Tool::verifyconnect();
    }

    /**
     * Cette fonction retourne tous les users, sous forme d'un tableau permettant l'administration de ces users.
     *
     * @param mixed $page
     */
    public function list($page = 0)
    {
        $page = (int) $page;
        $users = $this->internal_user->list(25, $page);
        $this->render('user/list', ['users' => $users]);
    }

    /**
     * Cette fonction va supprimer une liste de users.
     *
     * @param array int $_GET['ids'] : Les id des useres à supprimer
     * @param mixed     $csrf
     *
     * @return boolean;
     */
    public function delete($csrf)
    {
        if (!$this->verify_csrf($csrf))
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Jeton CSRF invalid !');

            return $this->redirect(\descartes\Router::url('User', 'list'));
        }

        if (!\controllers\internals\Tool::is_admin())
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Vous devez être administrateur pour supprimer un utilisateur !');

            return $this->redirect(\descartes\Router::url('User', 'list'));
        }

        $ids = $_GET['ids'] ?? [];
        foreach ($ids as $id)
        {
            $this->internal_user->delete($id);
        }

        return $this->redirect(\descartes\Router::url('User', 'list'));
    }

    /**
     * Cette fonction retourne la page d'ajout d'un user.
     */
    public function add()
    {
        return $this->render('user/add');
    }

    /**
     * Cette fonction insert un nouveau user.
     *
     * @param $csrf : Le jeton CSRF
     * @param string           $_POST['email']            : L'email de l'utilisateur
     * @param string           $_POST['email_confirm']    : Verif de l'email de l'utilisateur
     * @param optional string  $_POST['password']         : Le mot de passe de l'utilisateur (si vide, généré automatiquement)
     * @param optional string  $_POST['password_confirm'] : Confirmation du mot de passe de l'utilisateur
     * @param optional boolean $_POST['admin']            : Si vrai, l'utilisateur est admin, si vide non
     */
    public function create($csrf)
    {
        if (!$this->verify_csrf($csrf))
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Jeton CSRF invalid !');

            return $this->redirect(\descartes\Router::url('User', 'add'));
        }

        $email = $_POST['email'] ?? false;
        $password = $_POST['password'] ?? \controllers\internals\Tool::generate_password(rand(6, 12));
        $admin = $_POST['admin'] ?? false;

        if (!$email)
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Vous devez au moins fournir une adresse e-mail pour l\'utilisateur.');

            return $this->redirect(\descartes\Router::url('User', 'add'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'L\'adresse e-mail n\'est pas valide.');

            return $this->redirect(\descartes\Router::url('User', 'add'));
        }

        $email_send = \controllers\internals\Tool::send_email($email, EMAIL_CREATE_USER, ['email' => $email, 'password' => $password]);
        if (!$email_send)
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Impossible d\'envoyer l\'e-mail à l\'utilisateur, le compte n\'a donc pas été créé.');

            return $this->redirect(\descartes\Router::url('User', 'add'));
        }

        $user_id = $this->internal_user->create($email, $password, $admin);
        if (!$user_id)
        {
            \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('danger', 'Impossible de créer ce user.');

            return $this->redirect(\descartes\Router::url('User', 'add'));
        }

        \modules\DescartesSessionMessages\internals\DescartesSessionMessages::push('success', 'L\'utilisateur a bien été créé.');

        return $this->redirect(\descartes\Router::url('User', 'list'));
    }
}
<?php

namespace App\Policies;

use App\Models\PhonebookFolder;
use App\Models\User;

class PhonebookFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('phonebook.manage');
    }

    public function view(User $user, PhonebookFolder $phonebookFolder): bool
    {
        return $user->can('phonebook.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('phonebook.manage');
    }

    public function update(User $user, PhonebookFolder $phonebookFolder): bool
    {
        return $user->can('phonebook.manage');
    }

    public function delete(User $user, PhonebookFolder $phonebookFolder): bool
    {
        return $user->can('phonebook.manage');
    }

    public function manageContacts(User $user, PhonebookFolder $phonebookFolder): bool
    {
        return $user->can('phonebook.manage');
    }
}

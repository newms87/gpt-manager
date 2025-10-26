<?php

namespace App\Policies;

use App\Models\Billing\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->checkTeamOwnership($user, $paymentMethod);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->checkTeamOwnership($user, $paymentMethod);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->checkTeamOwnership($user, $paymentMethod);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->checkTeamOwnership($user, $paymentMethod);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->checkTeamOwnership($user, $paymentMethod);
    }

    /**
     * Check if the user's current team owns the payment method.
     */
    private function checkTeamOwnership(User $user, PaymentMethod $paymentMethod): bool
    {
        $currentTeam = $user->currentTeam;

        return $currentTeam && $paymentMethod->team_id === $currentTeam->id;
    }
}

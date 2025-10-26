<?php

namespace App\Repositories\Billing;

use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class PaymentMethodRepository extends ActionRepository
{
    public static string $model = PaymentMethod::class;

    public function query(): Builder
    {
        return parent::query()
            ->where('team_id', team()->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');
    }

    public function applyAction(string $action, PaymentMethod|Model|array|null $model = null, ?array $data = null)
    {
        return match ($action) {
            'create'       => $this->createPaymentMethod($data),
            'update'       => $this->updatePaymentMethod($model, $data),
            'make-default' => $this->makeDefaultPaymentMethod($model),
            'delete'       => $this->deletePaymentMethod($model),
            default        => parent::applyAction($action, $model, $data)
        };
    }

    public function getDefaultPaymentMethodForTeam(): ?PaymentMethod
    {
        return PaymentMethod::forTeam(team()->id)->default()->first();
    }

    public function getTeamPaymentMethods(int $teamId): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentMethod::where('team_id', $teamId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    protected function createPaymentMethod(array $data): PaymentMethod
    {
        $this->validateTeamOwnership();

        $data['team_id'] = team()->id;

        $paymentMethod = new PaymentMethod($data);
        $paymentMethod->validate();
        $paymentMethod->save();

        // If this is the first payment method for the team, make it default
        $existingCount = PaymentMethod::forTeam(team()->id)->count();
        if ($existingCount === 1) {
            $paymentMethod->makeDefault();
        }

        return $paymentMethod->fresh();
    }

    protected function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $this->validateOwnership($paymentMethod);

        $paymentMethod->fill($data);
        $paymentMethod->validate();
        $paymentMethod->save();

        return $paymentMethod->fresh();
    }

    protected function makeDefaultPaymentMethod(PaymentMethod $paymentMethod): PaymentMethod
    {
        $this->validateOwnership($paymentMethod);

        if ($paymentMethod->is_default) {
            throw new ValidationError('Payment method is already the default', 400);
        }

        $paymentMethod->makeDefault();

        return $paymentMethod->fresh();
    }

    protected function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $this->validateOwnership($paymentMethod);

        // Check if this is the only payment method
        $paymentMethodCount = PaymentMethod::forTeam($paymentMethod->team_id)->count();
        if ($paymentMethodCount === 1) {
            throw new ValidationError('Cannot delete the only payment method', 400);
        }

        // If this was the default, make another one default
        if ($paymentMethod->is_default) {
            $nextPaymentMethod = PaymentMethod::forTeam($paymentMethod->team_id)
                ->where('id', '!=', $paymentMethod->id)
                ->first();

            if ($nextPaymentMethod) {
                $nextPaymentMethod->makeDefault();
            }
        }

        return $paymentMethod->delete();
    }

    protected function validateOwnership(PaymentMethod $paymentMethod): void
    {
        $currentTeam = team();
        if (!$currentTeam || $paymentMethod->team_id !== $currentTeam->id) {
            throw new ValidationError('You do not have permission to access this payment method', 403);
        }
    }

    protected function validateTeamOwnership(): void
    {
        $currentTeam = team();
        if (!$currentTeam) {
            throw new ValidationError('No team context available', 403);
        }
    }
}

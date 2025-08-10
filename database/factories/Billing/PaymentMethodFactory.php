<?php

namespace Database\Factories\Billing;

use App\Models\Billing\PaymentMethod;
use App\Models\Team\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'stripe_payment_method_id' => 'pm_' . $this->faker->uuid(),
            'type' => 'card',
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']),
            'card_last_four' => $this->faker->numerify('####'),
            'card_exp_month' => $this->faker->numberBetween(1, 12),
            'card_exp_year' => $this->faker->numberBetween(date('Y'), date('Y') + 10),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function visa(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_brand' => 'visa',
            'card_last_four' => '4242',
        ]);
    }

    public function mastercard(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_brand' => 'mastercard',
            'card_last_four' => '4444',
        ]);
    }

    public function amex(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_brand' => 'amex',
            'card_last_four' => '0005',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'card_exp_month' => 12,
            'card_exp_year' => date('Y') - 1,
        ]);
    }

    public function nonCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank_account',
            'card_brand' => null,
            'card_last_four' => null,
            'card_exp_month' => null,
            'card_exp_year' => null,
        ]);
    }
}
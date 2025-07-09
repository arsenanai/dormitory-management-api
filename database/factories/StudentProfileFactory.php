<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentProfile>
 */
class StudentProfileFactory extends Factory {
	protected $model = StudentProfile::class;

	/**
	 * Define the model's default state.
	 *
	 * @return array<string, mixed>
	 */
	public function definition(): array {
		return [ 
			'user_id'                        => User::factory(),
			'student_id'                     => fake()->unique()->numerify( 'STU#####' ),
			'faculty'                        => fake()->randomElement( [ 'Engineering', 'Business', 'Medicine', 'Law', 'Arts' ] ),
			'specialist'                     => fake()->randomElement( [ 'Computer Science', 'Mechanical Engineering', 'Accounting', 'Marketing' ] ),
			'course'                         => fake()->numberBetween( 1, 4 ),
			'year_of_study'                  => fake()->numberBetween( 1, 4 ),
			'enrollment_year'                => fake()->year(),
			'enrollment_date'                => fake()->dateTimeBetween( '-4 years', 'now' ),
			'blood_type'                     => fake()->randomElement( [ 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' ] ),
			'violations'                     => fake()->optional()->sentence(),
			'parent_name'                    => fake()->name(),
			'parent_phone'                   => fake()->phoneNumber(),
			'parent_email'                   => fake()->optional()->email(),
			'guardian_name'                  => fake()->optional()->name(),
			'guardian_phone'                 => fake()->optional()->phoneNumber(),
			'mentor_name'                    => fake()->optional()->name(),
			'mentor_email'                   => fake()->optional()->email(),
			'emergency_contact_name'         => fake()->name(),
			'emergency_contact_phone'        => fake()->phoneNumber(),
			'emergency_contact_relationship' => fake()->randomElement( [ 'Father', 'Mother', 'Guardian', 'Sibling', 'Uncle', 'Aunt' ] ),
			'medical_conditions'             => fake()->optional()->sentence(),
			'dietary_restrictions'           => fake()->optional()->randomElement( [ 'Vegetarian', 'Vegan', 'Halal', 'Kosher', 'Gluten-free' ] ),
			'program'                        => fake()->randomElement( [ 'Computer Science', 'Engineering', 'Business', 'Medicine', 'Law' ] ),
			'year_level'                     => fake()->numberBetween( 1, 4 ),
			'nationality'                    => fake()->country(),
			'deal_number'                    => fake()->optional()->numerify( 'DEAL#####' ),
			'agree_to_dormitory_rules'       => fake()->boolean(),
			'has_meal_plan'                  => fake()->boolean(),
			'registration_limit_reached'     => fake()->boolean( 20 ),
			'is_backup_list'                 => fake()->boolean( 30 ),
		];
	}
}

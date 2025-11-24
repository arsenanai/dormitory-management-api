<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string|null $position
 * @property string|null $department
 * @property string|null $office_phone
 * @property string|null $office_location
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $dormitory_id
 * @property-read \App\Models\Dormitory|null $dormitory
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AdminProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereDepartment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereDormitoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereOfficeLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereOfficePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminProfile whereUserId($value)
 */
	class AdminProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $room_id
 * @property int $bed_number
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_occupied
 * @property bool $reserved_for_staff
 * @property-read \App\Models\Room $room
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\BedFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereBedNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereIsOccupied($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereReservedForStaff($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bed whereUserId($value)
 */
	class Bed extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BloodType whereUpdatedAt($value)
 */
	class BloodType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property string|null $description
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Configuration whereValue($value)
 */
	class Configuration extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property string|null $description
 * @property string $gender
 * @property int $capacity
 * @property string|null $phone
 * @property int|null $admin_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $admin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Room> $rooms
 * @property-read int|null $rooms_count
 * @method static \Database\Factories\DormitoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereAdminId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dormitory whereUpdatedAt($value)
 */
	class Dormitory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string|null $purpose_of_visit
 * @property string|null $host_name
 * @property string|null $host_contact
 * @property string|null $visit_start_date
 * @property string|null $visit_end_date
 * @property string|null $identification_type
 * @property string|null $identification_number
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property bool $is_approved
 * @property string $daily_rate
 * @property string|null $reminder
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $bed_id
 * @property-read \App\Models\Bed|null $bed
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\GuestProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereBedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereDailyRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereEmergencyContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereEmergencyContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereHostContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereHostName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereIdentificationNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereIdentificationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereIsApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile wherePurposeOfVisit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereReminder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereVisitEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GuestProfile whereVisitStartDate($value)
 */
	class GuestProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $sender_id
 * @property string $title
 * @property string $content
 * @property string $recipient_type
 * @property int|null $dormitory_id
 * @property int|null $room_id
 * @property array<array-key, mixed>|null $recipient_ids
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $receiver_id
 * @property string $type
 * @property-read \App\Models\Dormitory|null $dormitory
 * @property-read \App\Models\User|null $receiver
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\User $sender
 * @method static \Database\Factories\MessageFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDormitoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReceiverId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereRecipientIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereRecipientType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSenderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message withoutTrashed()
 */
	class Message extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $payment_check
 * @property string|null $deal_number
 * @property \Illuminate\Support\Carbon|null $deal_date
 * @property \Illuminate\Support\Carbon|null $date_from
 * @property \Illuminate\Support\Carbon|null $date_to
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereDateFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereDateTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereDealDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereDealNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUserId($value)
 */
	class Payment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\RoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 */
	class Role extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $number
 * @property int|null $floor
 * @property string|null $notes
 * @property int $dormitory_id
 * @property int $room_type_id
 * @property int|null $quota
 * @property string $occupant_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $is_occupied
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bed> $beds
 * @property-read int|null $beds_count
 * @property-read \App\Models\Dormitory $dormitory
 * @property-read \App\Models\RoomType $roomType
 * @method static \Database\Factories\RoomFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereDormitoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereFloor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereIsOccupied($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereOccupantType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereQuota($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereRoomTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Room whereUpdatedAt($value)
 */
	class Room extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $minimap
 * @property array<array-key, mixed>|null $beds
 * @property array<array-key, mixed>|null $photos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $capacity
 * @property numeric $daily_rate
 * @property numeric $semester_rate
 * @method static \Database\Factories\RoomTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereBeds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereDailyRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereMinimap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType wherePhotos($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereSemesterRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RoomType whereUpdatedAt($value)
 */
	class RoomType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $iin
 * @property string $student_id
 * @property string|null $faculty
 * @property string|null $specialist
 * @property string|null $course
 * @property int|null $year_of_study
 * @property int|null $enrollment_year
 * @property string|null $enrollment_date
 * @property string|null $blood_type
 * @property string|null $violations
 * @property string|null $parent_name
 * @property string|null $parent_phone
 * @property string|null $parent_email
 * @property string|null $guardian_name
 * @property string|null $guardian_phone
 * @property string|null $mentor_name
 * @property string|null $mentor_email
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property string|null $medical_conditions
 * @property string|null $dietary_restrictions
 * @property string|null $program
 * @property int|null $year_level
 * @property string|null $nationality
 * @property string|null $deal_number
 * @property bool $agree_to_dormitory_rules
 * @property bool $has_meal_plan
 * @property bool $registration_limit_reached
 * @property bool $is_backup_list
 * @property string|null $date_of_birth
 * @property string|null $gender
 * @property string|null $allergies
 * @property array<array-key, mixed>|null $files
 * @property int|null $city_id
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\StudentProfileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereAgreeToDormitoryRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereAllergies($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereBloodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereDealNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereDietaryRestrictions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereEmergencyContactName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereEmergencyContactPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereEmergencyContactRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereEnrollmentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereEnrollmentYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereFaculty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereGuardianName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereGuardianPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereHasMealPlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereIin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereIsBackupList($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereMedicalConditions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereMentorEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereMentorName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereNationality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereParentEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereParentName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereParentPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereProgram($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereRegistrationLimitReached($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereSpecialist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereViolations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereYearLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudentProfile whereYearOfStudy($value)
 */
	class StudentProfile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property array<array-key, mixed>|null $phone_numbers
 * @property int|null $room_id
 * @property string $password
 * @property int|null $role_id
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $student_id
 * @property string|null $birth_date
 * @property string|null $blood_type
 * @property string|null $course
 * @property string|null $faculty
 * @property string|null $specialty
 * @property int|null $enrollment_year
 * @property int|null $graduation_year
 * @property string|null $gender
 * @property string|null $emergency_contact
 * @property string|null $emergency_phone
 * @property string|null $violations
 * @property string $status
 * @property int|null $year_of_study
 * @property string|null $phone
 * @property int $has_meal_plan
 * @property string|null $iin
 * @property string|null $date_of_birth
 * @property int|null $city_id
 * @property string|null $files
 * @property string|null $card_number
 * @property int|null $dormitory_id
 * @property-read \App\Models\Dormitory|null $adminDormitory
 * @property-read \App\Models\AdminProfile|null $adminProfile
 * @property-read \App\Models\Payment|null $currentSemesterPayment
 * @property-read \App\Models\GuestProfile|null $guestProfile
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \App\Models\Role|null $role
 * @property-read \App\Models\Bed|null $studentBed
 * @property-read \App\Models\StudentProfile|null $studentProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBloodType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCardNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCourse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDormitoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmergencyContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmergencyPhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEnrollmentYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFaculty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGraduationYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereHasMealPlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhoneNumbers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSpecialty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStudentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereViolations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereYearOfStudy($value)
 */
	class User extends \Eloquent {}
}


# Running only "related" (done-task) tests

Full filtered command with the list of test files only.

## Requirements for coverage

Code coverage requires **PCOV** or **Xdebug** (with coverage mode). Without one of these, tests run but no coverage is collected and no report is shown.

- **PCOV**: `pecl install pcov` then enable in php.ini
- **Xdebug**: enable coverage in php.ini, e.g. `xdebug.mode=coverage`

## Full command (terminal)

```bash
cd crm-back

php artisan test \
  tests/Feature/StudentIdentificationFieldsTest.php \
  tests/Feature/StudentPhotoUploadTest.php \
  tests/Feature/StudentRegistrationDateTest.php \
  tests/Feature/PaymentSemesterFilterTest.php \
  tests/Feature/RoomTypePhotosTest.php \
  tests/Feature/DormitoryContactFieldsTest.php \
  tests/Feature/AdminDormitoryOptionalTest.php \
  tests/Feature/PaymentTypeTest.php \
  tests/Feature/StudentEmergencyContactTest.php \
  tests/Feature/EmailAvailabilityTest.php \
  tests/Feature/DormitoryRegistrationTest.php \
  tests/Feature/GuestPaymentSyncAndMailTest.php \
  tests/Feature/PaymentStatusUpdateTest.php \
  --coverage
```

With `--coverage`, the text report is printed to the terminal only (via a custom `test` command that runs PHPUnit with `--coverage-text`).

## Test files in the list

- AdminDormitoryOptionalTest
- DormitoryContactFieldsTest
- DormitoryRegistrationTest
- EmailAvailabilityTest
- GuestPaymentSyncAndMailTest
- PaymentSemesterFilterTest
- PaymentStatusUpdateTest
- PaymentTypeTest
- RoomTypePhotosTest
- StudentEmergencyContactTest
- StudentIdentificationFieldsTest
- StudentPhotoUploadTest
- StudentRegistrationDateTest

These map to done tasks: emergency contact, rooms without free beds, identification fields, student photo, registration date, semester filter, room type photos, dormitory contacts, admin dormitory optional, payment types, emails, payment status/guest sync.

## Excluded from Related (currently failing elsewhere)

- StudentControllerTest (3 failures: auth expectation, JSON path, gender/dormitory)
- AutoPaymentCreationTest (1 failure: gender/dormitory)
- Unit tests: GuestRoomAvailabilityTest, RoomServiceAvailableTest, RoomTypeServiceTest, RoomTypeValidationTest, FileServiceTest(s), GuestServiceTest, StudentServiceTest, DevelopmentSeederTest

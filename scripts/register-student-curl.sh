#!/usr/bin/env bash
# Simulates frontend student registration (FormData) via curl.
# Run after: php artisan migrate:fresh --seed && php artisan serve
# Usage: ./scripts/register-student-curl.sh [BASE_URL] [DORMITORY_ID] [ROOM_ID] [BED_ID] [AVATAR_FILE]
# AVATAR_FILE is optional; if set, sends as student_profile[files][2] (avatar). Example: public/assets/sdu-logo.png

set -e

BASE_URL="${1:-http://localhost:8000/api}"
DORMITORY_ID="${2:-1}"
ROOM_ID="${3:-51}"
BED_ID="${4:-91}"
AVATAR_FILE="${5:-}"

# Unique email and IIN for this run
SUFFIX="$(date +%s)"
EMAIL="curl-student-${SUFFIX}@example.com"
IIN="1234567890${SUFFIX: -2}"

echo "POST $BASE_URL/register (student)"
echo "Dormitory: $DORMITORY_ID, Room: $ROOM_ID, Bed: $BED_ID"
echo "Email: $EMAIL, IIN: $IIN"
[ -n "$AVATAR_FILE" ] && echo "Avatar: $AVATAR_FILE"
echo ""

CURL_ARGS=(
  -s -X POST "$BASE_URL/register"
  -H "Accept: application/json"
  -F "user_type=student"
  -F "locale=en"
  -F "first_name=Curl"
  -F "last_name=Student"
  -F "email=$EMAIL"
  -F "password=password123"
  -F "password_confirmation=password123"
  -F "dormitory_id=$DORMITORY_ID"
  -F "room_id=$ROOM_ID"
  -F "bed_id=$BED_ID"
  -F "phone_numbers[0]=+77001234567"
  -F "student_profile[gender]=male"
  -F "student_profile[iin]=$IIN"
  -F "student_profile[faculty]=Engineering"
  -F "student_profile[specialist]=Computer Science"
  -F "student_profile[enrollment_year]=2026"
  -F "student_profile[identification_type]=national_id"
  -F "student_profile[identification_number]=123456"
  -F "student_profile[agree_to_dormitory_rules]=1"
  -F "student_profile[country]=Kazakhstan"
  -F "student_profile[region]=Almaty Region"
  -F "student_profile[city]=Almaty"
  -F "student_profile[emergency_contact_name]=Jane Doe"
  -F "student_profile[emergency_contact_type]=parent"
  -F "student_profile[emergency_contact_phone]=+77007654321"
  -F "student_profile[emergency_contact_email]=jane@example.com"
  -F "student_profile[blood_type]=A+"
  -F "student_profile[allergies]=None"
  -F "student_profile[violations]="
)

if [ -n "$AVATAR_FILE" ] && [ -f "$AVATAR_FILE" ]; then
  CURL_ARGS+=(-F "student_profile[files][2]=@$AVATAR_FILE;type=image/png")
fi

curl "${CURL_ARGS[@]}" | jq .

echo ""
echo "To verify profile: login as this user, then GET $BASE_URL/users/profile (with Bearer token)."

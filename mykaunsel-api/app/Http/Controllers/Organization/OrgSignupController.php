<?php

namespace App\Http\Controllers\Organization;

use App\Enums\JoinMethod;
use App\Enums\JoinSource;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Enums\OrgStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationSignupRequest;
use App\Models\LocationPhoto;
use App\Models\Membership;
use App\Models\OrgDomain;
use App\Models\Organization;
use App\Models\PracticeLocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrgSignupController extends Controller
{
    /**
     * Maps the domain-role labels shown in the wizard to real membership roles.
     * "Manager" has no dedicated role tier yet, so it maps to Employee.
     */
    private const DOMAIN_ROLE_MAP = [
        'Student' => MembershipRole::Student,
        'Staff' => MembershipRole::Staff,
        'Employee' => MembershipRole::Employee,
        'Manager' => MembershipRole::Employee,
        'Counselor' => MembershipRole::Counselor,
    ];

    public function create(Request $request)
    {
        return view('organizations.signup.create');
    }

    public function store(StoreOrganizationSignupRequest $request)
    {
        $data = $request->validated();
        $isClinic = $data['org_type'] === 'clinic';
        $noDomain = $isClinic || $request->boolean('no_domain') || empty($data['domains'] ?? []);

        $organization = DB::transaction(function () use ($request, $data, $isClinic, $noDomain) {
            $organization = Organization::create([
                'name' => $data['org_name'],
                'org_type' => $data['org_type'],
                'ssm_number' => $data['ssm_number'] ?? null,
                'access_model' => $data['access_model'],
                'subscription_status' => OrgStatus::Pending,
                'subscription_tier' => $data['org_size'] ?? null,
                'join_method' => $isClinic ? null : ($noDomain ? JoinMethod::InviteCode : JoinMethod::Domain),
            ]);

            if (! $isClinic && ! $noDomain) {
                foreach ($data['domains'] as $domainRow) {
                    OrgDomain::create([
                        'org_id' => $organization->id,
                        'domain' => Str::lower(trim($domainRow['domain'])),
                        'default_role' => self::DOMAIN_ROLE_MAP[$domainRow['role']] ?? MembershipRole::Staff,
                    ]);
                }
            }

            $this->storeLocation($request, $organization, $isClinic, $data);

            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
            ]);

            Membership::create([
                'user_id' => $admin->id,
                'org_id' => $organization->id,
                'role' => MembershipRole::OrgAdmin,
                'title' => $data['admin_title'],
                'status' => MembershipStatus::Active,
                'join_source' => JoinSource::Manual,
                'joined_at' => now(),
            ]);

            Auth::login($admin);

            return $organization;
        });

        if (! $isClinic && ! $noDomain) {
            return redirect()->route('organizations.signup.verify-domain', $organization);
        }

        return redirect()->route('organizations.signup.waiting-approval', $organization);
    }

    private function storeLocation(Request $request, Organization $organization, bool $isClinic, array $data): void
    {
        $locationInput = $isClinic ? ($data['location'] ?? null) : ($data['opt_location'] ?? null);

        if (! $locationInput || blank($locationInput['name'] ?? null)) {
            return;
        }

        $location = PracticeLocation::create([
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
            'name' => $locationInput['name'],
            'address' => $locationInput['address'] ?? '',
            'city' => $locationInput['city'] ?? '',
            'state' => $locationInput['state'] ?? '',
            'postcode' => $locationInput['postcode'] ?? '',
            'latitude' => $locationInput['latitude'] ?? null,
            'longitude' => $locationInput['longitude'] ?? null,
            'is_primary' => true,
        ]);

        if ($isClinic && $request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $photo) {
                $path = $photo->store('location-photos', 'public');

                LocationPhoto::create([
                    'practice_location_id' => $location->id,
                    'photo_url' => Storage::url($path),
                    'sort_order' => $index,
                ]);
            }
        }
    }
}

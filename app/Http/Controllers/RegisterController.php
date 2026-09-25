<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\SubDistrict;
use App\Models\WasteBank;
use App\Services\BankMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    public function show()
    {
        return view('register', [
            'wasteBanks' => WasteBank::query()
                ->with(['district', 'subDistrict'])
                ->where('status', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function register(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'address' => 'required|string|max:255',
            'district' => 'required|integer|exists:districts,id',
            'sub_district' => 'required|integer|exists:sub_districts,id',
            'waste_bank_id' => 'required|integer',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'waste_bank_id.required' => 'Bank Sampah wajib dipilih.',
            'waste_bank_id.integer' => 'Bank Sampah yang dipilih tidak valid.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        if (! SubDistrict::query()
            ->whereKey($request->integer('sub_district'))
            ->where('district_id', $request->integer('district'))
            ->exists()) {
            return redirect()->back()
                ->withErrors(['sub_district' => 'The sub-district must belong to the selected district.'])
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        $kodeKota = '001'; // Bontang
        $kodeDistrict = str_pad($request->district, 2, '0', STR_PAD_LEFT);
        $kodeSubDistrict = str_pad($request->sub_district, 2, '0', STR_PAD_LEFT);
        // ambil tahun berjalan
        $tahun = date('Y');

        // generate angka random 4 digit
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // gabungkan jadi format ID
        $number = $kodeKota.$kodeDistrict.$kodeSubDistrict.$tahun.$randomNumber;

        app(BankMembershipService::class)->registerCitizen([
            'number' => $number,
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
            'district_id' => $request->district,
            'sub_district_id' => $request->sub_district,
            'password' => Hash::make($request->password),
        ], $request->integer('waste_bank_id'));

        return redirect('/register')->with('success', 'Pendaftaran berhasil. Akun Anda menunggu aktivasi dari pengelola.');
    }

    public function district()
    {
        $districts = District::pluck('name', 'id');

        return response()->json($districts);
    }

    public function subDistrict(Request $request)
    {
        $query = SubDistrict::query();

        if ($request->has('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        $subDistricts = $query->pluck('name', 'id');

        return response()->json($subDistricts);
    }
}

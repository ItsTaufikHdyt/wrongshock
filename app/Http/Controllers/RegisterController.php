<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;


class RegisterController extends Controller
{

    public function register(Request $request)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|max:255|unique:users',
            'address'               => 'required|string|max:255',
            'district'              => 'required|string|max:100',
            'sub_district'          => 'required|string|max:100',
            'password'              => 'required|string|min:6|confirmed',
        ], [
            'email.unique'          => 'Email ini sudah terdaftar.',
            'password.confirmed'    => 'Konfirmasi password tidak sama.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $userRole = Role::firstOrCreate(['name' => 'user']);

        $user = Auth::user();
        $kodeKota = '001'; // Bontang
        $kodeDistrict = str_pad($request->district, 2, '0', STR_PAD_LEFT);
        $kodeSubDistrict = str_pad($request->sub_district, 2, '0', STR_PAD_LEFT);
        // ambil tahun berjalan
        $tahun = date('Y');

        // generate angka random 4 digit
        $randomNumber = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        // gabungkan jadi format ID
        $number = $kodeKota . $kodeDistrict . $kodeSubDistrict . $tahun . $randomNumber;

        // Simpan ke database
        $user = User::create([
            'number'            => $number, // contoh format nomor
            'name'              => $request->name,
            'email'             => $request->email,
            'address'           => $request->address,
            'district_id'       => $request->district,
            'sub_district_id'   => $request->sub_district,
            'password'          => Hash::make($request->password),
            'status'            => 0, // tidak aktif
            'balance'           => 0, // saldo awal
        ]);
        $user->assignRole($userRole);

        return redirect('/register')->with('success', 'Registrasi berhasil! Silahkan Hubungin Admin.');
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

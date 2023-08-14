<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Parkir;
use Carbon\Carbon;
use PDF;

class ParkirController extends Controller
{
    //
    public function set_masuk(Request $request)
    {
        $token = JWTAuth::getToken();
        $apy = JWTAuth::getPayload($token)->toArray();

        $role = User::find($apy['sub'])->role;
        /*
        role admin dan user dapat menginput
        */
        if ($role) {

            $no_polisi = $request->no_polisi;

            $cekNoPolisi = Parkir::where('no_polisi', $no_polisi)->where('status', 'in')->first();
            if ($cekNoPolisi) {
                return response()->json([
                    'success' => true,
                    'message' => 'kendaraan belum keluar'
                ], 400);
            }

            $data = Parkir::create([
                'no_polisi' => $no_polisi,
                'status' => 'in',
                'waktu_masuk' => Carbon::now(),
                // 'waktu_keluar' => '0000-00-00 00:00:00',
                'user_id' => $apy['sub'],
                'total' => 3000

            ]);

            if ($data) {
                return response()->json([
                    'success' => true,
                    'message' => 'kendaraan tercatat!',
                    'data' => $data
                ], 200);
            }
        }
    }

    public function set_keluar(Request $request)
    {
        $token = JWTAuth::getToken();
        $apy = JWTAuth::getPayload($token)->toArray();

        $role = User::find($apy['sub'])->role;

        /*
        role admin dan user dapat menginput
        */

        if ($role) {

            $no_polisi = $request->no_polisi;
            $tiket = $request->no_tiket;

            $cekTiket = Parkir::where('id', $tiket)->where('status', 'out')->first();
            if ($cekTiket) {
                return response()->json([
                    'success' => true,
                    'message' => 'kendaraan belum masuk'
                ], 400);
            }

            $waktu_masuk = Parkir::where('id', $tiket)->where('status', 'in')->first()->waktu_masuk;
            $total = Parkir::where('id', $tiket)->where('status', 'in')->first()->total;

            $now = Carbon::now();
            $date1 = date_create($now);
            $date2 = date_create($waktu_masuk);
            $diff = date_diff($date1, $date2);
            $lama = $diff->h;



            Parkir::where('id', $tiket)->update([
                'status' => 'out',
                'waktu_keluar' => Carbon::now(),
                'total' => (3000 * $lama) + $total,
                'user_id' => $apy['sub']

            ]);

            $data = Parkir::find($tiket);



            if ($data) {
                return response()->json([
                    'success' => true,
                    'message' => 'kendaraan tercatat!',
                    'data' => $data
                ], 200);
            }
        }
    }

    public function laporan(Request $request)
    {
        $token = JWTAuth::getToken();
        $apy = JWTAuth::getPayload($token)->toArray();

        $role = User::find($apy['sub'])->role;

        $tgl_awal = $request->input('tgl_awal', '');
        $tgl_akhir = $request->input('tgl_akhir', '');

        /*
        role admin dapat melihat
        */
        if ($role != 'admin') {
            return response()->json([
                'success' => true,
                'message' => 'bukan admin!'
            ], 400);
        }

        $data = Parkir::whereBetween('created_at', [$tgl_awal, $tgl_akhir])->get();

        if (!$data->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'data ditemukan!',
                'data' => $data
            ], 200);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'data tidak ditemukan!'
            ], 400);
        }
    }

    public function export_laporan(Request $request)
    {

        /*
        role admin dapat mengexport
        */
        $token = JWTAuth::getToken();
        $apy = JWTAuth::getPayload($token)->toArray();

        $role = User::find($apy['sub'])->role;

        $tgl_awal = $request->input('tgl_awal', '');
        $tgl_akhir = $request->input('tgl_akhir', '');

        /*
        role admin dapat melihat
        */
        if ($role != 'admin') {
            return response()->json([
                'success' => true,
                'message' => 'bukan admin!'
            ], 400);
        }

        $data = Parkir::whereBetween('created_at', [$tgl_awal, $tgl_akhir])->get();


        // Generate the PDF using a view
        $html = '<h1>Parking Report</h1>';
        $html .= '<table border=1 width=100%>';
        $html .= '<thead><tr>
        <th>No Tiket</th>
        <th>No Polisi</th>
        <th>Waktu Masuk</th>
        <th>Waktu Keluar</th>
        <th>Total</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($data as $item) {
            $html .= '<tr>
            <td>' . $item->id . '</td>
            <td>' . $item->no_polisi . '</td>
            <td>' . $item->waktu_masuk . '</td>
            <td>' . $item->waktu_keluar . '</td>
            <td>Rp. ' . $item->total . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        // Generate the PDF
        $pdf = PDF::loadHTML($html);

        // Download the PDF
        return $pdf->download('parkir_report.pdf');
    }
}

<?php
// database/migrations/2024_01_01_000001_create_wilayas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('wilayas', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('nom');
            $table->string('nom_arabe')->nullable();
            $table->string('region')->nullable();
            $table->timestamps();
        });

        // Insérer les 58 wilayas d'Algérie
        $wilayas = [
            ['01', 'Adrar', 'أدرار'],
            ['02', 'Chlef', 'الشلف'],
            ['03', 'Laghouat', 'الأغواط'],
            ['04', 'Oum El Bouaghi', 'أم البواقي'],
            ['05', 'Batna', 'باتنة'],
            ['06', 'Béjaïa', 'بجاية'],
            ['07', 'Biskra', 'بسكرة'],
            ['08', 'Béchar', 'بشار'],
            ['09', 'Blida', 'البليدة'],
            ['10', 'Bouira', 'البويرة'],
            ['11', 'Tamanrasset', 'تمنراست'],
            ['12', 'Tébessa', 'تبسة'],
            ['13', 'Tlemcen', 'تلمسان'],
            ['14', 'Tiaret', 'تيارت'],
            ['15', 'Tizi Ouzou', 'تيزي وزو'],
            ['16', 'Alger', 'الجزائر'],
            ['17', 'Djelfa', 'الجلفة'],
            ['18', 'Jijel', 'جيجل'],
            ['19', 'Sétif', 'سطيف'],
            ['20', 'Saïda', 'سعيدة'],
            ['21', 'Skikda', 'سكيكدة'],
            ['22', 'Sidi Bel Abbès', 'سيدي بلعباس'],
            ['23', 'Annaba', 'عنابة'],
            ['24', 'Guelma', 'قالمة'],
            ['25', 'Constantine', 'قسنطينة'],
            ['26', 'Médéa', 'المدية'],
            ['27', 'Mostaganem', 'مستغانم'],
            ['28', 'M\'Sila', 'المسيلة'],
            ['29', 'Mascara', 'معسكر'],
            ['30', 'Ouargla', 'ورقلة'],
            ['31', 'Oran', 'وهران'],
            ['32', 'El Bayadh', 'البيض'],
            ['33', 'Illizi', 'إليزي'],
            ['34', 'Bordj Bou Arréridj', 'برج بوعريريج'],
            ['35', 'Boumerdès', 'بومرداس'],
            ['36', 'El Tarf', 'الطارف'],
            ['37', 'Tindouf', 'تندوف'],
            ['38', 'Tissemsilt', 'تيسمسيلت'],
            ['39', 'El Oued', 'الوادي'],
            ['40', 'Khenchela', 'خنشلة'],
            ['41', 'Souk Ahras', 'سوق أهراس'],
            ['42', 'Tipaza', 'تيبازة'],
            ['43', 'Mila', 'ميلة'],
            ['44', 'Aïn Defla', 'عين الدفلى'],
            ['45', 'Naâma', 'النعامة'],
            ['46', 'Aïn Témouchent', 'عين تموشنت'],
            ['47', 'Ghardaïa', 'غرداية'],
            ['48', 'Relizane', 'غليزان'],
            ['49', 'Timimoun', 'تيميمون'],
            ['50', 'Bordj Badji Mokhtar', 'برج باجي مختار'],
            ['51', 'Ouled Djellal', 'أولاد جلال'],
            ['52', 'Béni Abbès', 'بني عباس'],
            ['53', 'In Salah', 'عين صالح'],
            ['54', 'In Guezzam', 'عين قزام'],
            ['55', 'Touggourt', 'تقرت'],
            ['56', 'Djanet', 'جانت'],
            ['57', 'El M\'Ghair', 'المغير'],
            ['58', 'El Meniaa', 'المنيعة'],
        ];

        foreach ($wilayas as $wilaya) {
            DB::table('wilayas')->insert([
                'code' => $wilaya[0],
                'nom' => $wilaya[1],
                'nom_arabe' => $wilaya[2],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('wilayas');
    }
};

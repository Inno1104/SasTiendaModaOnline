<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecuador_ubicaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('provincia', 100);
            $table->string('canton', 100);
            $table->unsignedSmallInteger('orden_provincia');
            $table->unsignedSmallInteger('orden_canton');
            $table->unique(['provincia', 'canton']);
            $table->index(['orden_provincia', 'orden_canton']);
        });

        $catalog = [
            'Azuay' => ['Cuenca','Girón','Gualaceo','Nabón','Paute','Pucará','San Fernando','Santa Isabel','Sígsig','Oña','Chordeleg','El Pan','Sevilla de Oro','Guachapala','Camilo Ponce Enríquez'],
            'Bolívar' => ['Guaranda','Chillanes','Chimbo','Echeandía','San Miguel','Caluma','Las Naves'],
            'Cañar' => ['Azogues','Biblián','Cañar','La Troncal','El Tambo','Déleg','Suscal'],
            'Carchi' => ['Tulcán','Bolívar','Espejo','Mira','Montúfar','San Pedro de Huaca'],
            'Cotopaxi' => ['Latacunga','La Maná','Pangua','Pujilí','Salcedo','Saquisilí','Sigchos'],
            'Chimborazo' => ['Riobamba','Alausí','Colta','Chambo','Chunchi','Guamote','Guano','Pallatanga','Penipe','Cumandá'],
            'El Oro' => ['Machala','Arenillas','Atahualpa','Balsas','Chilla','El Guabo','Huaquillas','Marcabelí','Pasaje','Piñas','Portovelo','Santa Rosa','Zaruma','Las Lajas'],
            'Esmeraldas' => ['Esmeraldas','Eloy Alfaro','Muisne','Quinindé','San Lorenzo','Atacames','Rioverde'],
            'Guayas' => ['Guayaquil','Alfredo Baquerizo Moreno','Balao','Balzar','Colimes','Daule','Durán','El Empalme','El Triunfo','Milagro','Naranjal','Naranjito','Palestina','Pedro Carbo','Samborondón','Santa Lucía','Salitre','San Jacinto de Yaguachi','Playas','Simón Bolívar','Coronel Marcelino Maridueña','Lomas de Sargentillo','Nobol','General Antonio Elizalde','Isidro Ayora'],
            'Imbabura' => ['Ibarra','Antonio Ante','Cotacachi','Otavalo','Pimampiro','San Miguel de Urcuquí'],
            'Loja' => ['Loja','Calvas','Catamayo','Celica','Chaguarpamba','Espíndola','Gonzanamá','Macará','Paltas','Puyango','Saraguro','Sozoranga','Zapotillo','Pindal','Quilanga','Olmedo'],
            'Los Ríos' => ['Babahoyo','Baba','Montalvo','Puebloviejo','Quevedo','Urdaneta','Ventanas','Vinces','Palenque','Buena Fe','Valencia','Mocache','Quinsaloma'],
            'Manabí' => ['Portoviejo','Bolívar','Chone','El Carmen','Flavio Alfaro','Jipijapa','Junín','Manta','Montecristi','Paján','Pichincha','Rocafuerte','Santa Ana','Sucre','Tosagua','24 de Mayo','Pedernales','Olmedo','Puerto López','Jama','Jaramijó','San Vicente'],
            'Morona Santiago' => ['Morona','Gualaquiza','Limón Indanza','Palora','Santiago','Sucúa','Huamboya','San Juan Bosco','Taisha','Logroño','Pablo Sexto','Tiwintza'],
            'Napo' => ['Tena','Archidona','El Chaco','Quijos','Carlos Julio Arosemena Tola'],
            'Pastaza' => ['Pastaza','Mera','Santa Clara','Arajuno'],
            'Pichincha' => ['Quito','Cayambe','Mejía','Pedro Moncayo','Rumiñahui','San Miguel de los Bancos','Pedro Vicente Maldonado','Puerto Quito'],
            'Tungurahua' => ['Ambato','Baños de Agua Santa','Cevallos','Mocha','Patate','Quero','San Pedro de Pelileo','Santiago de Píllaro','Tisaleo'],
            'Zamora Chinchipe' => ['Zamora','Chinchipe','Nangaritza','Yacuambi','Yantzaza','El Pangui','Centinela del Cóndor','Palanda','Paquisha'],
            'Galápagos' => ['San Cristóbal','Isabela','Santa Cruz'],
            'Sucumbíos' => ['Lago Agrio','Gonzalo Pizarro','Putumayo','Shushufindi','Sucumbíos','Cascales','Cuyabeno'],
            'Orellana' => ['Francisco de Orellana','Aguarico','La Joya de los Sachas','Loreto'],
            'Santo Domingo de los Tsáchilas' => ['Santo Domingo','La Concordia'],
            'Santa Elena' => ['Santa Elena','La Libertad','Salinas'],
        ];

        $rows = [];
        foreach ($catalog as $provinceIndex => $cantons) {
            foreach ($cantons as $cantonIndex => $canton) {
                $rows[] = [
                    'provincia' => $provinceIndex,
                    'canton' => $canton,
                    'orden_provincia' => array_search($provinceIndex, array_keys($catalog), true) + 1,
                    'orden_canton' => $cantonIndex + 1,
                ];
            }
        }
        DB::table('ecuador_ubicaciones')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('ecuador_ubicaciones');
    }
};

<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Seeder;

class DormitoryRulesSeeder extends Seeder
{
    /**
     * Seed default dormitory rules (fallback for all environments including production).
     * Only creates if not already set, so existing admin customizations are preserved.
     */
    public function run(): void
    {
        $exists = Configuration::where('key', 'dormitory_rules')->exists();
        if ($exists) {
            return;
        }

        $locales = [
            'en' => $this->getDefaultRulesEn(),
            'kk' => $this->getDefaultRulesKk(),
            'ru' => $this->getDefaultRulesRu(),
        ];

        Configuration::create([
            'key'         => 'dormitory_rules',
            'value'       => json_encode($locales),
            'type'        => 'json',
            'description' => 'Dormitory Rules and Regulations',
        ]);
    }

    private function getDefaultRulesEn(): string
    {
        return '<h2>Dormitory Rules and Regulations</h2>
<p>All residents must comply with the following rules. Violations may result in disciplinary action.</p>

<h3>1. General Conduct</h3>
<ul>
<li>Respect fellow residents, staff, and property at all times.</li>
<li>Maintain quiet hours from 22:00 to 07:00 on weekdays and 23:00 to 08:00 on weekends.</li>
<li>No alcohol, tobacco, or prohibited substances on the premises.</li>
</ul>

<h3>2. Room and Common Areas</h3>
<ul>
<li>Keep your room and shared spaces clean and tidy.</li>
<li>Do not move furniture or make structural changes without permission.</li>
<li>Report any maintenance issues to the front desk promptly.</li>
</ul>

<h3>3. Visitors and Guests</h3>
<ul>
<li>Register all visitors at the front desk.</li>
<li>Visitors must leave by 23:00 unless pre-approved.</li>
<li>You are responsible for your guests\' behavior.</li>
</ul>

<h3>4. Safety and Emergencies</h3>
<ul>
<li>Know the location of fire exits and extinguishers.</li>
<li>Do not block corridors or emergency exits.</li>
<li>In case of emergency, follow staff instructions and evacuate calmly.</li>
</ul>

<p><strong>By registering, you acknowledge that you have read and agree to these rules.</strong></p>';
    }

    private function getDefaultRulesKk(): string
    {
        return '<h2>Жатақхана ережелері мен қағидалары</h2>
<p>Барлық тұрғындар келесі ережелерді сақтауға міндетті. Бұзушылықтар тәртіптік шараларға әкелуі мүмкін.</p>

<h3>1. Жалпы мінез-құлық</h3>
<ul>
<li>Барлық уақытта қонақтармен, қызметкерлермен және мүлікпен сыйластықпен қарым-қатынас жасаңыз.</li>
<li>Жұма күндері 22:00-ден 07:00-ге дейін, демалыс күндері 23:00-ден 08:00-ге дейін тыныш уақытты сақтаңыз.</li>
<li>Территорияда алкоголь, темекі немесе тыйым салынған заттарға рұқсат етілмейді.</li>
</ul>

<h3>2. Бөлме және ортақ аумақтар</h3>
<ul>
<li>Бөлмені және ортақ жайларды таза және тәртіпте ұстаңыз.</li>
<li>Рұқсатсыз жиһазды жылжытпаңыз немесе құрылымдық өзгерістер енгізбеңіз.</li>
<li>Техникалық ақауларды дереу қызмет табсырына хабарлаңыз.</li>
</ul>

<h3>3. Қонақтар</h3>
<ul>
<li>Барлық қонақтарды қызмет табсырында тіркеңіз.</li>
<li>Алдын ала рұқсат берілмесе, қонақтар 23:00-ге дейін кетуі керек.</li>
<li>Қонақтарыңыздың мінез-құлығы үшін жауаптысыз.</li>
</ul>

<h3>4. Қауіпсіздік және төтенше жағдайлар</h3>
<ul>
<li>Өрт шығу жолдары мен өрт сөндіргіштердің орналасуын біліңіз.</li>
<li>Дәліздер мен төтенше шығу жолдарын бөгелемеңіз.</li>
<li>Төтенше жағдайда қызметкерлердің нұсқауларын орындаңыз және тыныш эвакуациялаңыз.</li>
</ul>

<p><strong>Тіркелу арқылы сіз осы ережелерді оқып, қабылдағаныңызды мойындайсыз.</strong></p>';
    }

    private function getDefaultRulesRu(): string
    {
        return '<h2>Правила и положения общежития</h2>
<p>Все проживающие обязаны соблюдать следующие правила. Нарушения могут повлечь дисциплинарные взыскания.</p>

<h3>1. Общее поведение</h3>
<ul>
<li>Уважайте проживающих, персонал и имущество в любое время.</li>
<li>Соблюдайте тихие часы с 22:00 до 07:00 в будни и с 23:00 до 08:00 в выходные.</li>
<li>Алкоголь, табак и запрещённые вещества на территории запрещены.</li>
</ul>

<h3>2. Комната и общие помещения</h3>
<ul>
<li>Содержите комнату и общие зоны в чистоте и порядке.</li>
<li>Не перемещайте мебель и не вносите структурные изменения без разрешения.</li>
<li>Сообщайте о неисправностях на стойку регистрации незамедлительно.</li>
</ul>

<h3>3. Посетители и гости</h3>
<ul>
<li>Регистрируйте всех посетителей на стойке регистрации.</li>
<li>Посетители должны покинуть общежитие до 23:00, если не получено предварительное разрешение.</li>
<li>Вы несёте ответственность за поведение своих гостей.</li>
</ul>

<h3>4. Безопасность и чрезвычайные ситуации</h3>
<ul>
<li>Знайте расположение запасных выходов и огнетушителей.</li>
<li>Не загромождайте коридоры и аварийные выходы.</li>
<li>В случае чрезвычайной ситуации следуйте указаниям персонала и эвакуируйтесь спокойно.</li>
</ul>

<p><strong>Регистрируясь, вы подтверждаете, что прочитали и согласны с этими правилами.</strong></p>';
    }
}

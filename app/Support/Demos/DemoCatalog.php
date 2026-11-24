<?php

namespace App\Support\Demos;

use App\Enums\Events\PageSection;
use App\Models\Events\EventGiftSetting;
use App\Models\Events\EventRsvpSetting;

/**
 * The demonstration events shown on the home page, one per occasion. Every
 * name, story and message here is made up. Their pictures live in
 * resources/demo/{slug} (see the README there); the date is never stored,
 * it moves along with today (see Event::showDemoMoment()).
 */
class DemoCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'demo-casamento' => self::wedding(),
            'demo-cha-de-bebe' => self::babyShower(),
            'demo-aniversario' => self::kidsBirthday(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function wedding(): array
    {
        return [
            'type' => 'casamento',
            'title' => 'Helena & Lucas',
            'event_time' => '16:30',
            'description' => "Depois de oito anos juntos, chegou a hora de dizer sim na frente de quem a gente mais ama.\n\nVenha celebrar com a gente!",
            'story' => "A gente se conheceu numa fila de cinema que não andava, discutindo qual filme valia mais a pena. Ninguém ganhou a discussão, mas saímos de lá com um jantar marcado.\n\nVieram as viagens de carro sem destino, um apartamento pequeno cheio de plantas e o Biscoito, nosso vira-lata que manda na casa. Em uma noite de inverno em Monte Verde, o Lucas se ajoelhou (e quase derrubou o fondue).\n\nAgora queremos você do nosso lado no dia mais feliz das nossas vidas.",
            'share_message' => "Oi, {nome}! Vamos nos casar e queremos muito você com a gente 💍\n\nConfirme sua presença: {link_confirmacao}\nNossa lista de presentes: {link_presentes}",
            'appearance' => [
                'primary_color' => '#f6f1ea',
                'secondary_color' => '#7d8f73',
                'button_color' => '#975a16',
                'font_color_primary' => '#3b3a30',
                'font_color_secondary' => '#6b6858',
                'font_family' => 'script',
                'cover_effect_intensity' => 20,
                'show_location_shortcut' => true,
            ],
            'rsvp' => [
                'collect_companions' => true,
                'max_companions' => 3,
                'fields' => EventRsvpSetting::DEFAULT_FIELDS,
                'companion_fields' => EventRsvpSetting::DEFAULT_COMPANION_FIELDS,
            ],
            'gifts' => [
                'display_mode' => EventGiftSetting::DISPLAY_LIST,
                'gift_message' => 'Sua presença é o nosso maior presente. Mas, se quiser nos mimar, preparamos esta lista com muito carinho.',
                'allow_in_person' => true,
                'allow_free_amount' => true,
            ],
            'sections' => [PageSection::Home, PageSection::Gallery, PageSection::Gifts, PageSection::Rsvp, PageSection::Guestbook, PageSection::Location],
            'locations' => [
                ['name' => 'Cerimônia', 'start_time' => '16:30', 'address' => 'Parque Villa-Lobos, Av. Prof. Fonseca Rodrigues, 2001 - Alto de Pinheiros, São Paulo - SP', 'latitude' => -23.5465, 'longitude' => -46.7234],
                ['name' => 'Festa', 'start_time' => '18:30', 'address' => 'Jardim Botânico de São Paulo, Av. Miguel Estéfano, 3031 - Água Funda, São Paulo - SP', 'latitude' => -23.6403, 'longitude' => -46.6236],
            ],
            'products' => [
                ['name' => 'Jantar romântico na lua de mel', 'description' => 'Uma noite especial à beira-mar em Fernando de Noronha.', 'price' => 350, 'quantity' => 4, 'image' => 'jantar'],
                ['name' => 'Passeio de barco', 'description' => 'Um dia inteiro navegando e mergulhando nas piscinas naturais.', 'price' => 480, 'quantity' => 2, 'image' => 'barco'],
                ['name' => 'Cota para as passagens', 'description' => 'Ajude a gente a chegar ao paraíso.', 'price' => 200, 'quantity' => 15, 'image' => 'passagens'],
                ['name' => 'Air fryer', 'description' => 'Para os jantares rápidos da vida de casados.', 'price' => 420, 'quantity' => 1, 'image' => 'air-fryer'],
                ['name' => 'Jogo de panelas', 'description' => 'Antiaderente, 5 peças, para cozinhar a dois.', 'price' => 560, 'quantity' => 1, 'image' => 'panelas'],
                ['name' => 'Máquina de café espresso', 'description' => 'Porque a gente não funciona antes do café.', 'price' => 890, 'quantity' => 1, 'image' => 'cafe'],
                ['name' => 'Jogo de cama king', 'description' => '100% algodão egípcio, 400 fios.', 'price' => 380, 'quantity' => 1, 'image' => 'cama'],
                ['name' => 'Kit de taças de cristal', 'description' => 'Para brindar todas as conquistas.', 'price' => 190, 'quantity' => 2, 'image' => 'tacas'],
                ['name' => 'Ração e petiscos para o Biscoito', 'description' => 'Ele também merece comemorar.', 'price' => 90, 'quantity' => 5, 'image' => 'biscoito'],
            ],
            'messages' => [
                ['author' => 'Tia Lúcia', 'message' => 'Vi esse amor nascer e crescer. Que a vida de vocês seja leve, cheia de risadas e de viagens sem destino. Amo vocês!'],
                ['author' => 'Marina e Paulo', 'message' => 'Que alegria ver os dois chegando nesse momento! Contem com a gente sempre. Já estamos ensaiando os passos na pista!'],
                ['author' => 'Vó Cida', 'message' => 'Meus netos queridos, que Deus abençoe essa união. Rezo todos os dias pela felicidade de vocês.'],
                ['author' => 'Turma da faculdade', 'message' => 'Quem diria que aquela discussão no cinema ia dar casamento? Vocês são inspiração. Viva os noivos!'],
                ['author' => null, 'message' => 'Desejo uma vida inteira de parceria, paciência e muito amor. Felicidades!'],
                ['author' => 'Bruno', 'message' => 'Lucas, meu irmão, finalmente alguém que aguenta suas piadas. Helena, bem-vinda oficialmente à família!'],
            ],
            'albums' => ['Pré-wedding', 'Cerimônia', 'Festa'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function babyShower(): array
    {
        return [
            'type' => 'cha_bebe',
            'title' => 'Chá do Theo',
            'event_time' => '15:00',
            'description' => "O Theo está chegando e a gente quer comemorar com você!\n\nUma tarde de brincadeiras, docinhos e muito carinho para receber o mais novo membro da família.",
            'story' => "Foram meses de enjoo, desejo de pastel de feira às onze da noite e muitas horas montando um berço que tinha mais parafusos que instruções.\n\nO Theo deve chegar no fim do verão e já é o centro das atenções: chuta toda vez que o papai canta (desafinado).\n\nPreparamos este dia para dividir essa alegria com quem vai fazer parte da vida dele.",
            'share_message' => "Oi, {nome}! O Theo está chegando e queremos comemorar com você 💙\n\nConfirme sua presença no chá: {link_confirmacao}",
            'appearance' => [
                'primary_color' => '#eef4f8',
                'secondary_color' => '#6d9dc5',
                'button_color' => '#2b6cb0',
                'font_color_primary' => '#2c3e50',
                'font_color_secondary' => '#5d6d7e',
                'font_family' => 'classic',
                'cover_effect_intensity' => 20,
                'show_location_shortcut' => true,
            ],
            'rsvp' => [
                'collect_companions' => true,
                'max_companions' => 4,
                'child_age_limit' => 12,
                'fields' => EventRsvpSetting::DEFAULT_FIELDS,
                'companion_fields' => [...EventRsvpSetting::DEFAULT_COMPANION_FIELDS, 'age' => EventRsvpSetting::FIELD_OPTIONAL],
            ],
            'gifts' => [
                'display_mode' => EventGiftSetting::DISPLAY_LIST,
                'gift_message' => 'Sua presença já deixa o Theo feliz! Se quiser presentear, montamos uma lista com o que ele vai precisar.',
                'allow_in_person' => true,
                'allow_free_amount' => true,
            ],
            'sections' => [PageSection::Home, PageSection::Rsvp, PageSection::Gifts, PageSection::Gallery, PageSection::Guestbook, PageSection::Location],
            'locations' => [
                ['name' => 'Chá do Theo', 'start_time' => '15:00', 'address' => 'Parque da Água Branca, Av. Francisco Matarazzo, 455 - Água Branca, São Paulo - SP', 'latitude' => -23.5277, 'longitude' => -46.6702],
            ],
            'products' => [
                ['name' => 'Pacote de fraldas P', 'description' => 'Para os primeiros meses (e são muitas!).', 'price' => 75, 'quantity' => 12, 'image' => 'fraldas-p'],
                ['name' => 'Pacote de fraldas M', 'description' => 'Ele vai crescer rápido.', 'price' => 85, 'quantity' => 12, 'image' => 'fraldas-m'],
                ['name' => 'Kit de bodies', 'description' => '5 bodies de algodão, tamanho RN a 3 meses.', 'price' => 120, 'quantity' => 3, 'image' => 'bodies'],
                ['name' => 'Banheira com trocador', 'description' => 'Para o banho mais gostoso do dia.', 'price' => 380, 'quantity' => 1, 'image' => 'banheira'],
                ['name' => 'Carrinho de passeio', 'description' => 'Para os passeios no parque.', 'price' => 1200, 'quantity' => 1, 'image' => 'carrinho'],
                ['name' => 'Babá eletrônica', 'description' => 'Com câmera, para os pais dormirem um pouco.', 'price' => 450, 'quantity' => 1, 'image' => 'baba'],
                ['name' => 'Mordedores e chocalhos', 'description' => 'Kit sensorial para os primeiros meses.', 'price' => 60, 'quantity' => 3, 'image' => 'mordedores'],
                ['name' => 'Livros de pano', 'description' => 'As primeiras histórias do Theo.', 'price' => 70, 'quantity' => 2, 'image' => 'livros'],
            ],
            'messages' => [
                ['author' => 'Dinda Carol', 'message' => 'Theo, você nem chegou e já é o bebê mais amado do mundo. A dinda está contando os dias para te encher de beijo!'],
                ['author' => 'Vovô Zé', 'message' => 'Meu primeiro neto! Já separei a vara de pescar pra gente. Que venha com saúde.'],
                ['author' => 'Colegas do escritório', 'message' => 'Desejamos uma chegada tranquila e muitas noites bem dormidas (a gente sabe que é pedir demais).'],
                ['author' => null, 'message' => 'Que o Theo cresça cercado de amor como esse. Parabéns, papais!'],
            ],
            'albums' => ['Ensaio gestante', 'O chá'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function kidsBirthday(): array
    {
        return [
            'type' => 'aniversario',
            'title' => 'Laura 6 anos',
            'event_time' => '14:00',
            'description' => "A Laura vai fazer 6 anos e a festa é no Jardim Encantado!\n\nTem pula-pula, oficina de pintura e bolo de morango. Venha brincar com a gente!",
            'story' => "A Laura ama borboletas, desenhar com todas as cores ao mesmo tempo e inventar músicas sobre o gato da vizinha.\n\nEste ano ela escolheu o tema sozinha: um jardim cheio de flores, fadas e, claro, muitas borboletas.\n\nVai ser uma tarde para brincar até cansar.",
            'share_message' => "Oi, {nome}! A Laura vai fazer 6 anos e a festa vai ser no Jardim Encantado 🦋\n\nConfirme a presença: {link_confirmacao}",
            'appearance' => [
                'primary_color' => '#fdf2f6',
                'secondary_color' => '#c77dab',
                'button_color' => '#8b2343',
                'font_color_primary' => '#4a2c3d',
                'font_color_secondary' => '#7a5a6b',
                'font_family' => 'serif',
                'cover_effect_intensity' => 15,
                'show_location_shortcut' => true,
            ],
            'rsvp' => [
                'collect_companions' => true,
                'max_companions' => 3,
                'child_age_limit' => 12,
                'fields' => EventRsvpSetting::DEFAULT_FIELDS,
                'companion_fields' => [...EventRsvpSetting::DEFAULT_COMPANION_FIELDS, 'age' => EventRsvpSetting::FIELD_REQUIRED],
            ],
            'gifts' => [
                'display_mode' => EventGiftSetting::DISPLAY_LIST,
                'gift_message' => 'O melhor presente é você na festa! Se quiser presentear, a Laura escolheu algumas ideias.',
                'allow_in_person' => true,
                'allow_free_amount' => false,
            ],
            'sections' => [PageSection::Home, PageSection::Rsvp, PageSection::Location, PageSection::Gifts, PageSection::Gallery, PageSection::Guestbook],
            'locations' => [
                ['name' => 'Festa', 'start_time' => '14:00', 'address' => 'Parque do Ibirapuera, Av. Pedro Álvares Cabral - Vila Mariana, São Paulo - SP', 'latitude' => -23.5874, 'longitude' => -46.6576],
            ],
            'products' => [
                ['name' => 'Kit de pintura com cavalete', 'description' => 'Tintas, pincéis e um cavalete do tamanho dela.', 'price' => 160, 'quantity' => 1, 'image' => 'pintura'],
                ['name' => 'Bicicleta aro 16', 'description' => 'Lilás, com cestinha (pedido especial).', 'price' => 650, 'quantity' => 1, 'image' => 'bicicleta'],
                ['name' => 'Coleção de livros ilustrados', 'description' => 'Histórias de fadas e jardins.', 'price' => 120, 'quantity' => 2, 'image' => 'livros'],
                ['name' => 'Fantasia de borboleta', 'description' => 'Com asas que brilham no escuro.', 'price' => 110, 'quantity' => 1, 'image' => 'fantasia'],
                ['name' => 'Jogo de tabuleiro', 'description' => 'Para jogar com a família no fim de semana.', 'price' => 90, 'quantity' => 2, 'image' => 'tabuleiro'],
                ['name' => 'Cota para aulas de natação', 'description' => 'Um mês de aulas que ela pediu muito.', 'price' => 100, 'quantity' => 6, 'image' => 'natacao'],
            ],
            'messages' => [
                ['author' => 'Tia Bia', 'message' => 'Laurinha, que seus 6 anos sejam tão coloridos quanto os seus desenhos! Te amo, borboletinha!'],
                ['author' => 'Família do Pedro', 'message' => 'O Pedro não fala de outra coisa além do pula-pula. Parabéns, Laura!'],
                ['author' => 'Professora Ana', 'message' => 'Feliz aniversário para a artista mais criativa da turma!'],
            ],
            'albums' => ['Preparativos', 'A festa'],
        ];
    }
}

# Imagens das demos

Fotos dos eventos de demonstração da página inicial (`php artisan demos:sync`). Os textos ficam em `app/Support/Demos/DemoCatalog.php`.

Use só imagens que você pode publicar: suas, geradas por IA ou de bancos com licença livre (Unsplash, Pexels). **Nada de fotos de eventos reais de clientes ou da família.** Formatos aceitos: `.jpg`, `.jpeg`, `.png` e `.webp`. Imagens até uns 2.000 px no lado maior bastam: o sistema gera as cópias leves da galeria sozinho.

Depois de trocar uma imagem, rode `php artisan demos:sync` de novo. O comando refaz as três demos do zero e lista as imagens que ainda faltam.

## demo-casamento (Helena & Lucas)

```
resources/demo/demo-casamento/
├── capa.jpg              ← capa da página (horizontal, 1600×1000 ou maior)
├── presentes/
│   ├── jantar.jpg                  ← Jantar romântico na lua de mel
│   ├── barco.jpg                   ← Passeio de barco
│   ├── passagens.jpg               ← Cota para as passagens
│   ├── air-fryer.jpg               ← Air fryer
│   ├── panelas.jpg                 ← Jogo de panelas
│   ├── cafe.jpg                    ← Máquina de café espresso
│   ├── cama.jpg                    ← Jogo de cama king
│   ├── tacas.jpg                   ← Kit de taças de cristal
│   ├── biscoito.jpg                ← Ração e petiscos para o Biscoito
└── albuns/
    ├── Pré-wedding/              ← quantas fotos quiser (a ordem segue o nome do arquivo)
    ├── Cerimônia/                ← quantas fotos quiser (a ordem segue o nome do arquivo)
    ├── Festa/                    ← quantas fotos quiser (a ordem segue o nome do arquivo)
```

## demo-cha-de-bebe (Chá do Theo)

```
resources/demo/demo-cha-de-bebe/
├── capa.jpg              ← capa da página (horizontal, 1600×1000 ou maior)
├── presentes/
│   ├── fraldas-p.jpg               ← Pacote de fraldas P
│   ├── fraldas-m.jpg               ← Pacote de fraldas M
│   ├── bodies.jpg                  ← Kit de bodies
│   ├── banheira.jpg                ← Banheira com trocador
│   ├── carrinho.jpg                ← Carrinho de passeio
│   ├── baba.jpg                    ← Babá eletrônica
│   ├── mordedores.jpg              ← Mordedores e chocalhos
│   ├── livros.jpg                  ← Livros de pano
└── albuns/
    ├── Ensaio gestante/          ← quantas fotos quiser (a ordem segue o nome do arquivo)
    ├── O chá/                    ← quantas fotos quiser (a ordem segue o nome do arquivo)
```

## demo-aniversario (Laura 6 anos)

```
resources/demo/demo-aniversario/
├── capa.jpg              ← capa da página (horizontal, 1600×1000 ou maior)
├── presentes/
│   ├── pintura.jpg                 ← Kit de pintura com cavalete
│   ├── bicicleta.jpg               ← Bicicleta aro 16
│   ├── livros.jpg                  ← Coleção de livros ilustrados
│   ├── fantasia.jpg                ← Fantasia de borboleta
│   ├── tabuleiro.jpg               ← Jogo de tabuleiro
│   ├── natacao.jpg                 ← Cota para aulas de natação
└── albuns/
    ├── Preparativos/             ← quantas fotos quiser (a ordem segue o nome do arquivo)
    ├── A festa/                  ← quantas fotos quiser (a ordem segue o nome do arquivo)
```

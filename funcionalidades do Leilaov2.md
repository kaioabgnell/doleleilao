Contexto do projeto : 

O sistema/site vai ser chamar [DOLELEILÃO] , o publico que vai se cadastrar no sistema vai ser empresas/pessoas que querem fazer leiloes dos seus itens de forma mais profissional. 
Entao a pessoa entra no site, preenche o formulario com dados pessoais e o nome da empresa de leilao , podendo ser cpf ou cnpj 
ela entra na plataforma com o perfil administrador e tem acessos aos cadastros/dashboards... 
depois que ela tem o perfil dela configurado ela vai ter um link unico para os clientes dela acessar o sistema que vai ser www.doleleilao.com.br/nome-do-leileiro-unido
ao ela compartilhar esse link com os as pessoas, as pessoas vao acessar a sua pagina unica onde pode ter cadsatro da banners/ categorias / leiloes, quando clicar nos leiloes sera apresentado os itens que vao entrar no leilao. 
para a pessoa participar no leilao, a pessoa vai ter que fazer um cadastro no site, colocar email. telefone, foto 
essa pessoa que fez o cadastro vai ter uma area pessoal para ela também, quando ela entrar vai ter acesso ao leiloes que ela está habilitada para participar , produtos favoritos, alertas de quando vai acontecer o leilao . 
uma area para mostrar as arrematacoes . 
O auditorio para acompanhar o leilao nao vai ser essa area pessao, vai ser no site publico por exemplo quando acessar em : 
www.doleleilao.com.br/nome-do-leileiro-unido/leilao/nome-do-leilao-escolhido
nessa tela vai ter o chat, -> porem so pode participar quem estiver logado. 

O leiloeiro deve ter a capacidade de customizar o layout da loja, por isso devemos ter 3 modelos : 
- Modelo com foco em Relogios 
- Modelo com foco em roupa 
- Modelo com foco em Acessorios eletronicos



funcionalidades do Leilao :


- Possibilidade de customizacao de cores e logo do portal dela; 
- Painel para msotrar os produtos que vao ser leiloados
- No painel de leilao, um chat em tempo real. 
- Plataforma escalavel ,
- Foco para pequenos e medio grupos de leilao 
- painel de cadastro de produtos. 
- analitcs para o admin ver quantas pessoas estao logados em tempo real 
- enviar mensagem para o vencedor o leilao 
- Painel vai ser dividido entre o dono do leilao, e os usuarios que se cadastram para participar do leilao 
- tudo vai acontecer em tempo real 
- cada cadastro vai ter uma url unica para acessar a plataforma

- os logins serao unicos por leiloeiros. 


Painel admin : 
- Controle de usuarios cadastrados  = com posisbilidade de banir um usuario 
- Cadastro de produtos - imagem/video, valor do produto, valor do inicial do leilao, valor de incremento , quantidade de visualizacoes no produto. 
- cadastro de um lote de leilao ; selecionar quais produtos vao; no cadsatro do leilao deve ter data e hora de inicio e configuracoes de leilao _ configuracao de chat , quem pode ver o chat ; 

Painel para o usaurio : 
- cadastro pessoal com dados pessoais , telefone email , foto 
- produtos disponivel - sendo possivel favoritar os produtos. 
- Proximos leiloes 
- Auditório Virtual
- chat 
- login
- cadastro do dados pessoais e validacao de email 
- gestao de itens arrematados; 
- quer leiloar algum produto seu ? fale com o leiloeiro. 


No auditorio de leilao :
configurar lances automaticos 
Mostrar o chat em tempo real com todas as conversar-> dar a opcao de resposder uma mensagem igual o whatsapp , dar a opcao de colocar emojis, 
na tela do ganhador , aparecer uma animacao de confetes com som falando "voce é o ganhador"



premissas do projeto

- deve ser desenvolvido com php 8.4
- frontend deve ser com next.js 
- banco de dados mysql
- nessa v1 nao sera desenvolvido um aplicativo , mas em um segundo momento tera um aplicativo que vai ser publicado nas lojas, entao deve construir a arquitetura já pensando nisso. 
- O layout deve ser mobile first 
- deve ter boa performance
- na parte do chat deve ter Comunicacao em Tempo Real, WebSocket, 

utilize skills :
wondelai/skills@clean-architecture
wondelai/skills@domain-driven-design

para Clean Architecture, DDD 

para UX : 
nextlevelbuilder/ui-ux-pro-max-skill@ui-ux-pro-max -> antes de codar, gera um design system completo para o projeto, com paleta, tipografia, espaçamento e estilo. Tem 119 regras de UX, 74 pares de fontes, 192 paletas por

nessa primeira etapa eu nao vou construir o site ainda, deixe instalado as skills de :
https://github.com/emilkowalski/skills
https://github.com/pbakaus/impeccable

essas 2 skills vao ser usadas para criar o site www.doleleilao.com.br

essas 2 skills tambem vao ser usadas para criar as 3 modelos de lojas:
- Modelo com foco em Relogios 
- Modelo com foco em roupa 
- Modelo com foco em Acessorios eletronicos


utilize a skill https://github.com/leonxlnx/taste-skill para se basear em site de verdade. 

-----

comercializacao desse saas; 

- na v1 os usuarios vao ter acesso completo o sistema
- na v2 ( desenvolvimento futuro) 
- a forma de rentabilizacao vai ser o leiloeiro definir quem vai assumir a taxa - o leiloeiro paga a taxa ou o usuario que comprou 
- produtos até R$ 100,00 -> 7.5%
- produtod de 100 a 500 -> 6%
- produtod de 500 a 1000 -> 5%
- acima de 1.000,00 -> 2.5%


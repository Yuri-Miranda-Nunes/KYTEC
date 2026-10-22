# Projeto Integrador – Controle de Estoque

O projeto foi desenvolvido pela equipe formada por João Vitor, Kathleen Daiane, Kauane Santos, Thiago Vinicius, Victor Yago e Yuri Miranda.

## Sobre a Empresa

A empresa **Euroquadros**, fundada na década de 1990 por **Marcos**, é um negócio familiar gerenciado atualmente por Luiz Antônio, Marcos Pereira, Lucas Yoshio, Nicholas Okino e Patrícia Mizue. A Euroquadros é especializada na criação de molduras, quadros, espelhos, porta-retratos e murais de fotos.

## Problematização

Durante a análise da empresa, foi identificado que um dos almoxarifados apresenta problemas relacionados ao **excessivo uso de papel** e **erros frequentes no controle de materiais**. Os colaboradores, ao retirarem os itens, muitas vezes anotam códigos errados, quantidades incorretas ou tamanhos equivocados dos produtos — como no caso de parafusos, por exemplo. Isso gera discrepâncias nos registros de estoque.

## Solução Proposta

A solução apresentada pela equipe é a **criação de um sistema de controle de estoque simples e eficiente**. A proposta inclui a utilização de **códigos de barras** nos crachás dos colaboradores e nos produtos. O processo seria:

1. O colaborador acessa um sistema com login compartilhado.
2. Aproxima seu crachá de um **leitor de código de barras**.
3. Faz o mesmo com o código do produto.
4. Digita a quantidade retirada e pressiona “Enter”.
5. A informação é registrada automaticamente em uma planilha de controle.

## Ligação com os Objetivos de Desenvolvimento Sustentável (ODS)

O projeto está alinhado com as **ODS 9 (Indústria, Inovação e Infraestrutura)** e **ODS 12 (Consumo e Produção Responsáveis)**, promovendo:

- Economia de papel;
- Agilidade no controle de estoque;
- Uso de tecnologias simples e acessíveis;
- Inovação nos processos industriais.

## Conexão com o Tema Gerador

A proposta conecta-se ao tema gerador do curso ao **solucionar um problema real** enfrentado por uma fábrica local em **Pindamonhangaba**. Através da automação simples via código de barras, promove-se a **sustentabilidade industrial**, combatendo desperdícios e melhorando a precisão no controle de materiais, especialmente em relação à ODS 12.

## Objetivos do Projeto

Com a implementação do sistema, espera-se:

- Tornar a **gestão de estoque mais eficiente e sustentável**;
- **Reduzir o uso de papel** e os erros humanos;
- Incentivar o uso **consciente de materiais**;
- **Aprimorar processos industriais**;
- Gerar **impacto ambiental positivo**, alinhado às práticas sustentáveis.

---

## Desenvolvimento Técnico

### Banco de Dados

Foi desenvolvido um banco de dados para armazenar e manipular as informações de usuários, produtos e movimentações de estoque.

### Mapa do Site

O projeto está organizado em seções principais:
1. Página Inicial  
2. Login  
3. Estoque  
4. Funcionário  

### Código

O código do projeto está disponível em um repositório no GitHub e foi desenvolvido de forma colaborativa:

- **Kathleen, Thiago e Yuri**: Implementação do código e banco de dados;
- **João e Victor**: Design da interface no Figma (Wireframe e Protótipo);
- **Kauane**: Responsável pela apresentação (slides), comunicação com a empresa e testes práticos com o leitor de código de barras.

### Protótipo e Wireframe

O design da aplicação foi feito no Figma, incluindo wireframes e protótipos interativos.  
**Link:** [Protótipo no Figma](https://www.figma.com/design/38UD7mOxF1vezmVPleT9UZ/Untitled?node-id=0-1&p=f&t=tAdwqig6psaUoio9-0)

---

# 📄 Documentação de Requisitos do Projeto

Este documento descreve os requisitos funcionais e não funcionais do projeto, consolidados a partir de múltiplas fontes para garantir um conjunto de especificações abrangente e sem redundâncias.

---

## ✅ Requisitos Funcionais (RF)

- **RF01: Cadastro de Usuários**  
  O sistema deve permitir o cadastro de usuários com informações como nome, e-mail, senha, perfil e permissões.

- **RF02: Login de Usuários**  
  O sistema deve possibilitar que os usuários façam login de forma segura usando suas credenciais.

- **RF03: Cadastro de Produtos**  
  O sistema deve permitir o cadastro de novos produtos com campos como nome, código, tipo, unidade, preço, estoque, categoria e outros detalhes relevantes.

- **RF04: Cadastro de Fornecedores**  
  O sistema deve suportar o cadastro completo de fornecedores, incluindo dados da empresa, informações do representante e CNPJ.

- **RF05: Gerenciamento de Usuários e Produtos**  
  O sistema deve permitir a criação, edição e exclusão lógica (desativação) de usuários, produtos e fornecedores para manter o histórico de registros.

- **RF06: Registro de Entrada de Estoque**  
  O sistema deve possibilitar o registro de entradas de produtos no estoque, incluindo fornecedor, quantidade, preço unitário, data e nota fiscal.

- **RF07: Pesquisa de Produtos**  
  O sistema deve permitir que os usuários pesquisem produtos por nome, código ou descrição.

- **RF08: Upload de Arquivos**  
  O sistema deve permitir que os usuários enviem arquivos, como documentos ou imagens.

- **RF09: Controle de Acesso**  
  O sistema deve garantir que apenas usuários autorizados acessem recursos específicos com base em seus níveis de permissão.

- **RF10: Validação de Dados**  
  O sistema deve validar os dados inseridos pelos usuários para garantir precisão e completude.

- **RF11: Retirada de Produtos**  
  O sistema deve incluir uma funcionalidade (por exemplo, um botão) para permitir que os usuários retirem produtos do estoque.

---

## ⚙️ Requisitos Não Funcionais (RNF)

- **RNF01: Interface Amigável**  
  O sistema deve ter uma interface web responsiva e intuitiva para facilitar o uso por todos os usuários, com um design de perfil de usuário simples e claro.

- **RNF02: Desempenho**  
  O sistema deve retornar informações para operações comuns em menos de 3 segundos.

- **RNF03: Armazenamento Seguro de Senhas**  
  Todas as senhas dos usuários devem ser criptografadas para garantir segurança.

- **RNF04: Proteção contra Injeção de SQL**  
  O sistema deve ser protegido contra ataques de injeção de SQL.

- **RNF05: Escalabilidade**  
  O sistema deve suportar um aumento de dez vezes no número de usuários e ser facilmente migrável para servidores pagos, se necessário.

- **RNF06: Compatibilidade com Navegadores**  
  O sistema deve funcionar corretamente nos navegadores Chrome, Firefox, Edge e Safari.

- **RNF07: Registro de Logs**  
  Todas as operações sensíveis devem ser registradas com detalhes, incluindo o usuário, a ação e o horário. O sistema também deve oferecer funcionalidades de filtragem para os logs.

- **RNF08: Manutenibilidade**  
  O código-fonte deve ser modular, bem documentado e fácil de manter ou expandir.

- **RNF09: Compatibilidade com Hospedagem**  
  O sistema deve operar de forma eficiente em ambientes com recursos limitados, como plataformas de hospedagem gratuitas (por exemplo, InfinityFree).

- **RNF10: Conformidade com Privacidade de Dados**  
  O sistema deve estar em conformidade com as leis de privacidade de dados aplicáveis.

---

## Demonstração online

O GitHub armazena o código, mas não executa PHP. Este repositório já possui um fluxo de publicação por FTP para uma hospedagem PHP; após configurar os segredos do GitHub, cada envio para `main` publica os arquivos da aplicação.

### Preparar a hospedagem

1. Troque as credenciais de FTP e do banco de dados que já foram incluídas no histórico do Git. Remover valores do código atual não invalida versões antigas.
2. No painel da hospedagem PHP, crie um banco MySQL novo e importe `database/demo.sql`. Esse arquivo contém estrutura e somente registros sintéticos; não importe o dump original, que pode conter dados pessoais.
3. Em **Settings > Secrets and variables > Actions** no GitHub, crie `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` com os novos dados da hospedagem. Não coloque os valores no repositório.
4. Confira se `server-dir` em `.github/workflows/main.yml` corresponde à pasta pública do provedor. O fluxo gera `config.local.php` apenas durante o deploy e publica a aplicação por FTP quando há push para `main`.
5. Envie as alterações para `main` e acompanhe **Actions** no GitHub. A URL pública depende do domínio criado pelo provedor de hospedagem.

### Acesso de demonstração

- **E-mail:** `demo@kytec.local`
- **Senha:** `KYTEC-Demo-Estoque-2026!`

Essa conta tem permissões administrativas para que visitantes experimentem cadastros e movimentações. Use-a exclusivamente com o banco isolado e fictício de `database/demo.sql`; todos os visitantes compartilham e podem alterar os mesmos dados. Se recriar o banco, a conta e a senha serão restauradas para esses valores públicos. Não reutilize essa senha em nenhum outro serviço.

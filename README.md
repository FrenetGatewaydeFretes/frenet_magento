<h1>Frenet - M&oacute;dulo Magento</h1>
**[www.frenet.com.br](http://www.www.frenet.com.br)**

<img src="http://painel.frenet.com.br/Content/images/Frenet_logo_painel.png" alt="Frenet - Gateway de fretes para e-commerce" title="Frenet - Gateway de fretes para e-commerce" />

<h2>Links</h2>
* [Painel Administrativo](http://painel.frenet.com.br)
* [Sistema de Tickets](http://suporte.lithiumsoftware.com.br)
* [Magento Connect](www.magentocommerce.com/magento-connect/)

<h2>Instalação</h2>

* ATENÇÃO! Recomendamos que sempre seja feito backup antes de realizar qualquer instalação de m&oacute;dulo

**Instalação manual**
**********************************************************************************************

* Baixe aqui a &uacute;ltima versão aqui, descompacte o arquivo baixado e copie a pasta app para a pasta principal da sua loja Magento

* Acesse a área administrativa de sua loja e limpe o cache em: Sistema > Gerenciamento de Cache

**Configuração**
**********************************************************************************************
* Acesse a área administrativa de sua loja e configure a nova forma de entrega instalada: Sistema > Configuração > Formas de Entrega > Frenet - Gateway de Fretes (conforme imagem a seguir)
<img src="https://s3-sa-east-1.amazonaws.com/magentoakhilleus/frenet_magento_admin.png"/>

- Configure os campos
  1. **Habilitado** - Habilite ou desabilite o módulo conforme sua necessidade
  2. **Título** - Nome do serviço de entrega que será exibido na loja para o cliente
  3. **Usuário** - Usuário de acesso ao webservice do Frenet (acesse o painel administrativo, em Dados Cadastrais, obtenha esta informação conforme imagem abaixo)
  4. **Senha** - Senha de acesso ao webservice do Frenet (acesse o painel administrativo, em Dados Cadastrais, obtenha esta informação conforme imagem abaixo)
  <br><img src="https://s3-sa-east-1.amazonaws.com/magentoakhilleus/frenet_magento_admin_access_key.png"/>
  5. **Utilizar dimensões padrão** - Caso esta opção seja marcada com <b>Sim</b>, o frete será calculado com as dimensões padrão preenchidas nos próximos campos. Caso seja preenchido com <b>Não</b>, o cálculo será baseado nas dimensões reais dos produtos cadastrados.
  6. **Altura Padrão (cm)** - Caso a opção "Utilizar dimensões padrão" esteja marcada com <b>Sim</b> sempre utiliza este valor para a altura dos produtos. Deve-se cadastrar um mínimo de 2 cm devido à limitação dos Correios.
  7. **Comprimento Padrão (cm)** - Caso a opção "Utilizar dimensões padrão" esteja marcada com <b>Sim</b> sempre utiliza este valor para o comprimento dos produtos. Deve-se cadastrar um mínimo de 16 cm devido à limitação dos Correios.
  8. **Largura Padrão (cm)** - Caso a opção "Utilizar dimensões padrão" esteja marcada com <b>Sim</b> sempre utiliza este valor para a largura dos produtos. Deve-se cadastrar um mínimo de 11 cm devido à limitação dos Correios.
  9. **Exibir Prazo de Entrega** - Marque esta opção com <b>Sim</b> caso queira exibir informações de prazo de entrega nos resultados de cotações de frete
  10. **Mensagem que exibe o prazo de entrega** - Caso a opção "Exibir Prazo de Entrega" esteja marcada com <br>Sim</b> o sistema irá montar uma mensagem amigavél ao usuário substituindo o %s e o %d respectivamente por método de entrega e quantidade de dias.
  11. **Adicionar ao prazo de entrega (dias)** - Quantidade de dias que será adicionado ao prazo de entrega de todas as cotações de frete
  12. **Ordem** - Ordem de exibição do módulo Frenet na tela "Formas de Entrega" dentre os outros módulos de entrega

  


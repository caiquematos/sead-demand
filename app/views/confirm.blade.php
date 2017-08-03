<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Demand SEaD</title>
	<style>
		@import url(//fonts.googleapis.com/css?family=Lato:700);

		body {
			margin:0;
			font-family:'Lato', sans-serif;
			text-align:center;
			color: #999;
		}

		.welcome {
			width: 300px;
			height: 200px;
			position: absolute;
			left: 50%;
			top: 50%;
			margin-left: -150px;
			margin-top: -100px;
		}

		a, a:visited {
			text-decoration:none;
		}

		h1 {
			font-size: 32px;
			margin: 16px 0 0 0;
		}
	</style>
</head>
<body>
	<div class="confirm center">
		<h2>Confirmação de Registro Demanda SEAD</h2>
		<p>Olá, {{$superiorname}}.<br>O seguinte usuário solicitou acesso ao Demanda SEAD:</p>
		<p>nome: {{$username}}<br>
		email: <a href="{{$useremail}}">{{$useremail}}</a><br>
		posição: {{$userposition}}</p>
		<p>Para confirmar o registro <a href="{{$link}}">clique aqui</a>.</p>
		<p>Atenciosamente,</p>
		<a href="http://sead.univasf.edu.br/" title="Versão Web">Demanda SEAD</a>
	</div>
</body>
</html>

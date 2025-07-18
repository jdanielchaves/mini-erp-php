<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar'])) {
    $nome = $_POST['nome'] ?? '';
    $descricao = $_POST['descricao'] ?? '';
    $preco = $_POST['preco'] ?? 0;
    $estoque = $_POST['estoque'] ?? 0;

    if ($nome && is_numeric($preco) && is_numeric($estoque)) {
        $sql = "INSERT INTO produtos (nome, descricao, preco, estoque) VALUES (:nome, :descricao, :preco, :estoque)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nome' => $nome,
            ':descricao' => $descricao,
            ':preco' => $preco,
            ':estoque' => $estoque,
        ]);
        header('Location: produtos.php');
        exit;
    } else {
        $erro = "Por favor, preencha os campos corretamente.";
    }
}

if (isset($_GET['excluir'])) {
    $idExcluir = intval($_GET['excluir']);
    $sql = "DELETE FROM produtos WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $idExcluir]);
    header('Location: produtos.php');
    exit;
}

$sql = "SELECT * FROM produtos ORDER BY id DESC";
$stmt = $pdo->query($sql);
$produtos = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>Produtos - Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="container py-4">

    <h1>Produtos</h1>

    <?php if (!empty($erro)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="mb-3">
            <label for="nome" class="form-label">Nome do Produto</label>
            <input type="text" id="nome" name="nome" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <textarea id="descricao" name="descricao" class="form-control"></textarea>
        </div>
        <div class="mb-3">
            <label for="preco" class="form-label">Preço (ex: 99.90)</label>
            <input type="number" step="0.01" id="preco" name="preco" class="form-control" required />
        </div>
        <div class="mb-3">
            <label for="estoque" class="form-label">Estoque</label>
            <input type="number" id="estoque" name="estoque" class="form-control" required />
        </div>
        <button type="submit" name="adicionar" class="btn btn-primary">Adicionar Produto</button>
    </form>

    <h2>Lista de Produtos</h2>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Descrição</th>
                <th>Preço</th>
                <th>Estoque</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars($p['nome']) ?></td>
                    <td><?= nl2br(htmlspecialchars($p['descricao'])) ?></td>
                    <td>R$ <?= number_format($p['preco'], 2, ',', '.') ?></td>
                    <td><?= $p['estoque'] ?></td>
                    <td>
                        <a href="?excluir=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Excluir este produto?')">Excluir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="6" class="text-center">Nenhum produto cadastrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>

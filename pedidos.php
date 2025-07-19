<?php
require 'db.php';

$erro = '';
$sucesso = '';

// Buscar produtos para o formulário
$sql = "SELECT * FROM produtos WHERE estoque > 0 ORDER BY nome";
$stmt = $pdo->query($sql);
$produtos = $stmt->fetchAll();

// Buscar cupons para validação 
$sqlCupons = "SELECT * FROM cupons WHERE ativo = 1 AND validade >= CURDATE()";
$stmtCupons = $pdo->query($sqlCupons);
$cuponsValidos = $stmtCupons->fetchAll(PDO::FETCH_UNIQUE);

// Processar envio do pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_pedido'])) {
    $cliente = trim($_POST['cliente_nome'] ?? '');
    $cupomCodigo = trim($_POST['cupom_codigo'] ?? '');

    // Produtos enviados via array: produto_id => quantidade
    $itens = $_POST['produtos'] ?? [];

    if (!$cliente) {
        $erro = "Informe o nome do cliente.";
    } elseif (empty($itens)) {
        $erro = "Selecione pelo menos um produto com quantidade.";
    } else {
        // Validar produtos e quantidades
        $itensValidos = [];
        $valorTotal = 0;

        foreach ($itens as $produtoId => $quantidade) {
            $quantidade = intval($quantidade);
            if ($quantidade < 1) continue;

            // Verificar se o produto existe e tem estoque
            $produto = null;
            foreach ($produtos as $p) {
                if ($p['id'] == $produtoId) {
                    $produto = $p;
                    break;
                }
            }
            if (!$produto) continue;
            if ($produto['estoque'] < $quantidade) {
                $erro = "Estoque insuficiente para o produto: " . htmlspecialchars($produto['nome']);
                break; 
            }

            $subtotal = $produto['preco'] * $quantidade;
            $itensValidos[] = [
                'produto' => $produto,
                'quantidade' => $quantidade,
                'subtotal' => $subtotal
            ];
            $valorTotal += $subtotal;
        }

        // Validar cupom
        $cupomId = null;
        $desconto = 0;
        if ($cupomCodigo) {
            $sqlCupom = "SELECT * FROM cupons WHERE codigo = :codigo AND ativo = 1 AND validade >= CURDATE()";
            $stmtCupom = $pdo->prepare($sqlCupom);
            $stmtCupom->execute([':codigo' => $cupomCodigo]);
            $cupom = $stmtCupom->fetch();

            if (!$cupom) {
                $erro = "Cupom inválido, inativo ou expirado.";
            } else {
                $cupomId = $cupom['id'];
                $desconto = $cupom['desconto'];
                $valorTotal = $valorTotal * ((100 - $desconto) / 100);
            }
        }

        if (!$erro) {
            $sqlPedido = "INSERT INTO pedidos (cliente_nome, valor_total, cupom_id, status) VALUES (:cliente, :valor_total, :cupom_id, 'pendente')";
            $stmtPedido = $pdo->prepare($sqlPedido);
            $stmtPedido->execute([
                ':cliente' => $cliente,
                ':valor_total' => $valorTotal,
                ':cupom_id' => $cupomId
            ]);
            $pedidoId = $pdo->lastInsertId();

            $sqlItem = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (:pedido_id, :produto_id, :quantidade, :preco_unitario, :subtotal)";
            $sqlEstoque = "UPDATE produtos SET estoque = estoque - :quantidade WHERE id = :produto_id";

            $stmtItem = $pdo->prepare($sqlItem);
            $stmtEstoque = $pdo->prepare($sqlEstoque);

            foreach ($itensValidos as $item) {
                $stmtItem->execute([
                    ':pedido_id' => $pedidoId,
                    ':produto_id' => $item['produto']['id'],
                    ':quantidade' => $item['quantidade'],
                    ':preco_unitario' => $item['produto']['preco'],
                    ':subtotal' => $item['subtotal']
                ]);
                $stmtEstoque->execute([
                    ':quantidade' => $item['quantidade'],
                    ':produto_id' => $item['produto']['id']
                ]);
            }

            $sucesso = "Pedido criado com sucesso!";
        }
    }
}

// Buscar pedidos para listagem
$sqlPedidos = "SELECT p.*, c.codigo AS cupom_codigo FROM pedidos p LEFT JOIN cupons c ON p.cupom_id = c.id ORDER BY p.id DESC";
$stmtPedidos = $pdo->query($sqlPedidos);
$pedidos = $stmtPedidos->fetchAll();

// Buscar itens para cada pedido
$pedidoItens = [];
if ($pedidos) {
    $pedidoIds = array_column($pedidos, 'id');
    $in = implode(',', $pedidoIds);
    $sqlItens = "SELECT * FROM pedido_itens WHERE pedido_id IN ($in)";
    $stmtItens = $pdo->query($sqlItens);
    $itensTodos = $stmtItens->fetchAll();

    foreach ($pedidos as $p) {
        $pedidoItens[$p['id']] = [];
    }
    foreach ($itensTodos as $item) {
        $pedidoItens[$item['pedido_id']][] = $item;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <title>Pedidos - Mini ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="container py-4">

    <h1>Pedidos</h1>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <form method="post" class="mb-5">

        <div class="mb-3">
            <label for="cliente_nome" class="form-label">Nome do Cliente</label>
            <input type="text" id="cliente_nome" name="cliente_nome" class="form-control" required value="<?= htmlspecialchars($_POST['cliente_nome'] ?? '') ?>" />
        </div>

        <h5>Produtos</h5>
        <?php if (empty($produtos)): ?>
            <p>Nenhum produto disponível no estoque.</p>
        <?php else: ?>
            <div class="mb-3">
                <?php foreach ($produtos as $produto): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="<?= $produto['id'] ?>" id="produto_<?= $produto['id'] ?>" name="produtos[<?= $produto['id'] ?>]" <?= (isset($_POST['produtos'][$produto['id']]) && $_POST['produtos'][$produto['id']] > 0) ? 'checked' : '' ?> />
                        <label class="form-check-label" for="produto_<?= $produto['id'] ?>">
                            <?= htmlspecialchars($produto['nome']) ?> (R$ <?= number_format($produto['preco'], 2, ',', '.') ?>) - Estoque: <?= $produto['estoque'] ?>
                        </label>
                        <input type="number" min="1" name="produtos[<?= $produto['id'] ?>]" value="<?= htmlspecialchars($_POST['produtos'][$produto['id']] ?? 1) ?>" class="form-control" style="width: 80px; display: inline-block; margin-left: 10px;" />
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label for="cupom_codigo" class="form-label">Código do Cupom</label>
            <input type="text" id="cupom_codigo" name="cupom_codigo" class="form-control" value="<?= htmlspecialchars($_POST['cupom_codigo'] ?? '') ?>" />
        </div>

        <button type="submit" name="criar_pedido" class="btn btn-primary">Criar Pedido</button>
    </form>

    <h2>Pedidos Cadastrados</h2>

    <?php if (empty($pedidos)): ?>
        <p>Nenhum pedido cadastrado.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Data</th>
                    <th>Valor Total</th>
                    <th>Cupom</th>
                    <th>Status</th>
                    <th>Itens</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><?= $pedido['id'] ?></td>
                        <td><?= htmlspecialchars($pedido['cliente_nome']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></td>
                        <td>R$ <?= number_format($pedido['valor_total'], 2, ',', '.') ?></td>
                        <td><?= htmlspecialchars($pedido['cupom_codigo'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pedido['status']) ?></td>
                        <td>
                            <ul>
                                <?php foreach ($pedidoItens[$pedido['id']] as $item): ?>
                                    <li>
                                        Produto ID <?= $item['produto_id'] ?> — Qtde: <?= $item['quantidade'] ?> — Subtotal: R$ <?= number_format($item['subtotal'], 2, ',', '.') ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>

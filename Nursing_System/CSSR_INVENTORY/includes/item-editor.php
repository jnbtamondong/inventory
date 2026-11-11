<?php
declare(strict_types=1);

function cssrItemEditorOptions(array $existingUnits = []): array
{
    $uniqueUnits = [];
    foreach (array_merge(['Piece','Box','Pack','Pair','Roll','Bottle','Set','Vial','Ampoule','Tube','Bag','Carton','Liter','Meter','Kit'], $existingUnits) as $unit) {
        if (!is_string($unit)) continue;
        $unit = trim($unit);
        if ($unit === '' || strlen($unit) > 50) continue;
        $key = strtolower($unit);
        if (!isset($uniqueUnits[$key])) $uniqueUnits[$key] = $unit;
    }
    $units = array_values($uniqueUnits);
    sort($units, SORT_NATURAL | SORT_FLAG_CASE);
    return ['classification'=>['MEDICAL SUPPLIES','MEDICAL INSTRUMENTS'], 'unit'=>$units,
        'storage_location'=>['STORAGE ROOM 1','STORAGE ROOM 2','STORAGE ROOM 3','CSSR'], 'procurement_type'=>['Procured','Donation','Emergency Purchase']];
}

function cssrValidateItemEditor(array $input, array $options): array
{
    $values = []; $errors = [];
    foreach (['item_name','item_code','classification','unit','item_description','minimum_stock','storage_location','procurement_type'] as $key) {
        $values[$key] = is_scalar($input[$key] ?? null) ? trim((string)$input[$key]) : '';
    }
    $values['auto_code'] = in_array($input['auto_code'] ?? null, ['1',1,true], true);
    $id = is_scalar($input['id'] ?? null) ? filter_var($input['id'],FILTER_VALIDATE_INT) : false;
    $values['id'] = $id === false ? 0 : $id;
    if (isset($input['id']) && $input['id'] !== '' && ($id === false || $id < 0)) $errors['_form'] = 'The item reference is invalid. Close the form and reopen the item.';
    $length = static fn(string $value): int => function_exists('mb_strlen') ? mb_strlen($value,'UTF-8') : strlen($value);
    if ($values['item_name'] === '') $errors['item_name'] = 'Enter an item name.';
    elseif ($length($values['item_name']) > 255) $errors['item_name'] = 'Use 255 characters or fewer.';
    if (!$values['auto_code']) {
        if ($values['item_code'] === '') $errors['item_code'] = 'Enter an item code or turn on auto-generate.';
        elseif ($length($values['item_code']) > 80) $errors['item_code'] = 'Use 80 characters or fewer.';
    }
    foreach ($options['unit'] as $unit) {
        if (strcasecmp($values['unit'], $unit) === 0) { $values['unit'] = $unit; break; }
    }
    foreach (['classification'=>'classification','unit'=>'unit','storage_location'=>'storage location','procurement_type'=>'procurement type'] as $key=>$label) {
        if (!in_array($values[$key],$options[$key],true)) $errors[$key] = 'Select a valid ' . $label . '.';
    }
    if ($length($values['item_description']) > 1000) $errors['item_description'] = 'Use 1,000 characters or fewer.';
    if (!preg_match('/^[0-9]+$/D',$values['minimum_stock']) || (float)$values['minimum_stock'] > 2147483647) $errors['minimum_stock'] = 'Enter a whole number from 0 to 2,147,483,647.';
    else $values['minimum_stock'] = (int)$values['minimum_stock'];
    return ['values'=>$values,'errors'=>$errors];
}

function cssrNextItemCode(PDO $connection): string
{
    $maximum = 0;
    $statement = $connection->query("SELECT item_code FROM items WHERE item_code LIKE 'CSSR-%'");
    while (($code = $statement->fetchColumn()) !== false) {
        if (preg_match('/^CSSR-([0-9]{1,15})$/iD', (string)$code, $match)) $maximum = max($maximum,(int)$match[1]);
    }
    return 'CSSR-' . str_pad((string)($maximum+1),6,'0',STR_PAD_LEFT);
}

// Call only after authentication, authorization and CSRF checks.
function cssrSaveItemEditor(PDO $connection, array $input, array $options): array
{
    $validation = cssrValidateItemEditor($input,$options);
    if ($validation['errors']) return ['ok'=>false,'errors'=>$validation['errors']];
    $value = $validation['values'];
    for ($attempt=0; $attempt<4; $attempt++) {
        try {
            $connection->beginTransaction();
            if ($value['id']) {
                $existing = $connection->prepare('SELECT id FROM items WHERE id=?' . ($connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
                $existing->execute([$value['id']]);
                if (!$existing->fetchColumn()) { $connection->rollBack(); return ['ok'=>false,'errors'=>['_form'=>'This item no longer exists. Refresh inventory and try again.']]; }
            }
            $code = $value['auto_code'] ? cssrNextItemCode($connection) : $value['item_code'];
            $duplicate = $connection->prepare('SELECT id FROM items WHERE UPPER(item_code)=UPPER(?) AND id<>? LIMIT 1');
            $duplicate->execute([$code,$value['id']]);
            if ($duplicate->fetchColumn()) {
                $connection->rollBack();
                if ($value['auto_code']) continue;
                return ['ok'=>false,'errors'=>['item_code'=>'This item code is already in use. Choose another code or use auto-generate.']];
            }
            $params = [$value['item_name'],$code,$value['classification'],$value['unit'],$value['item_description'],$value['minimum_stock'],$value['storage_location'],$value['procurement_type']];
            if ($value['id']) {
                // Editing defaults never transfers stock or replaces lot/receipt metadata.
                $statement = $connection->prepare('UPDATE items SET item_name=?,item_code=?,category=?,unit=?,item_description=?,minimum_stock=?,bin_location=?,procurement_type=? WHERE id=?');
                $statement->execute([...$params,$value['id']]);
                $id = $value['id'];
            } else {
                $statement = $connection->prepare("INSERT INTO items (item_name,item_code,category,unit,item_description,minimum_stock,bin_location,procurement_type,current_stock,status) VALUES (?,?,?,?,?,?,?,?,0,'Active')");
                $statement->execute($params); $id = (int)$connection->lastInsertId();
            }
            $connection->commit();
            return ['ok'=>true,'id'=>$id,'item_code'=>$code,'created'=>!$value['id']];
        } catch (PDOException $error) {
            if ($connection->inTransaction()) $connection->rollBack();
            $unique = (string)$error->getCode() === '23000' && ((int)($error->errorInfo[1] ?? 0) === 1062 || ((int)($error->errorInfo[1] ?? 0) === 19 && str_contains((string)($error->errorInfo[2] ?? ''),'UNIQUE')));
            if ($unique && $value['auto_code']) continue;
            if ($unique) return ['ok'=>false,'errors'=>['item_code'=>'This item code is already in use. Choose another code.']];
            throw $error;
        } catch (Throwable $error) {
            if ($connection->inTransaction()) $connection->rollBack();
            throw $error;
        }
    }
    return ['ok'=>false,'errors'=>['item_code'=>'A code could not be reserved. Please try saving again.']];
}

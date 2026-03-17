<?php 
//======================================================================
// CATEGORY LARGE FONT
//======================================================================

//-----------------------------------------------------
// Sub-Category Smaller Font
//-----------------------------------------------------

/* Title Here Notice the First Letters are Capitalized */

# Option 1
# Option 2
# Option 3

/*
 * This is a detailed explanation
 * of something that should require
 * several paragraphs of information.
 */
 
// This is a single line quote.
function gen_code8() {
  $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $resultado = '';
  $max = strlen($chars) - 1;
  for ($i = 0; $i < 8; $i++) {
      $r = random_int(0, $max);
      $resultado .= $chars[$r];
  }
  return $resultado;
}

function boton_add_pc($user_id,$computer_name,$api_token) {
  try {
      $db = getDB();
      $computer_code=gen_code8();
      $stmt = $db->prepare('SELECT computer_code FROM  computers WHERE computer_code = ?');
      $stmt->execute(array($computer_code));
      $stmtname = $db->prepare('SELECT computer_name FROM computers WHERE computer_name = ?');
      $stmtname->execute(array($computer_name));
      if ($stmtname->fetch()){
        return array('success' => false, 'message' => 'El nombre del ordenador ya está en uso');
      }else{
        if ($stmt->fetch()){
            // El codigo ya esta registrado, intentar de nuevo
            return boton_add_pc($user_id,$computer_name,$api_token);
        } else  {
            $stmt = $db->prepare('INSERT INTO computers (user_id, computer_code, computer_name, api_token) VALUES (?, ?, ?, ?)');
            $stmt->execute(array($user_id, $computer_code, $computer_name, $api_token));
            
            return array('success' => true, 'message' => 'Ordenador registrado correctamente', 'code' => $computer_code);
        }
      }

  } catch (PDOException $e) {
      return array('success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage());
  }
}
?>
<?php

require_once("bibliography.php");

function convertLaTeX($tag, $file, $code) {
  // get rid of things that should be HTML
  $code = preprocessCode($code);

  // this is the regex for all (sufficiently nice) text that can occur in things like \emph
  $regex = "[\p{L}\p{Nd}\?@\s$,.:()'&#;\-\\\\$]+";

  // fix special characters (&quot; should be " for \"e)
  $code = parseAccents(str_replace("&quot;", "\"", $code));

  // all big environments with their corresponding markup
  // TODO make this part of the code aware of the three types used in the TeX
  $environments = array(
    "lemma"       => array("Lemma", true),
    "definition"  => array("Definition", false),
    "remark"      => array("Remark", false),
    "remarks"     => array("Remarks", false),
    "example"     => array("Example", false),
    "theorem"     => array("Theorem", true),
    "exercise"    => array("Exercise", false),
    "situation"   => array("Situation", false),
    "proposition" => array("Proposition", true)
  );

  foreach ($environments as $environment => $information) {
    $count = preg_match_all("/\\\begin\{" . $environment . "\}\n\\\label\{([\w\-]*)\}/", $code, $matches);
    for ($i = 0; $i < $count; $i++) {
      $label = $file . '-' . $matches[1][$i];
      
      // check whether the label exists in the database, if not we cannot supply either a link or a number unfortunately
      if (labelExists($label))
        $code = str_replace($matches[0][$i], "<strong><a class='environment-link' href='" . getTagWithLabel($label) . "'>" . $information[0] . " " . getIDWithLabel($label) . ".</a></strong>" . ($information[1] ? '<em>' : ''), $code);
      else
        $code = str_replace($matches[0][$i], "<strong>" . $information[0] . ".</strong>" . ($information[1] ? '<em>' : ''), $code);
    }

    $count = preg_match_all("/\\\begin\{" . $environment . "\}\[(" . $regex . ")\]\n\\\label\{([\w\-]*)\}/u", $code, $matches);
    for ($i = 0; $i < $count; $i++) {
      $label = $file . '-' . $matches[2][$i];
      
      // check whether the label exists in the database, if not we cannot supply either a link or a number unfortunately
      if (labelExists($label))
        $code = str_replace($matches[0][$i], "<a class='environment-link' href='" . getTagWithLabel($label) . "'><strong>" . $information[0] . " " . getIDWithLabel($label) . "</strong> (" . $matches[1][$i] . ")<strong>.</strong></a>" . ($information[1] ? '<em>' : ''), $code);
      else
        $code = str_replace($matches[0][$i], "<strong>" . $information[0] . "</strong> (" . $matches[1][$i] . ")<strong>.</strong></a>" . ($information[1] ? '<em>' : ''), $code);
    }

    $code = str_replace("\\end{" . $environment . "}", ($information[1] ? '</em>' : '') . "</p>", $code);
  }

  $count = preg_match_all("/\\\begin\{equation\}\n\\\label\{([\w\-]+)\}\n/", $code, $matches);
  for ($i = 0; $i < $count; $i++) {
    $label = $file . '-' . $matches[1][$i];

    // check whether the label exists in the database, if not we cannot supply an equation number unfortunately
    if (labelExists($label))
      $code = str_replace($matches[0][$i], "\\begin{equation}\n\\tag{" . getIDWithLabel($label) . "}\n", $code);
    else
      $code = str_replace($matches[0][$i], "\\begin{equation}\n", $code);
  }

  // sections etc.
  $count = preg_match_all("/\\\section\{(" . $regex . ")\}\n\\\label\{([\w\-]+)\}/u", $code, $matches);
  for ($i = 0; $i < $count; $i++) {
    $label = $file . '-' . $matches[2][$i];

    // check whether the label exists in the database, if not we cannot supply either a link or a number unfortunately
    if (labelExists($label))
      $code = str_replace($matches[0][$i], "<h3>" . getIDWithLabel($label) . ". " . $matches[1][$i] . "</h3>", $code);
    else
      $code = str_replace($matches[0][$i], "<h3>" . $matches[1][$i] . "</h3>", $code);
  }

  $count = preg_match_all("/\\\subsection\{(" . $regex . ")\}\n\\\label\{([\w-]+)\}/u", $code, $matches);
  for ($i = 0; $i < $count; $i++) {
    $label = $file . '-' . $matches[2][$i];
    $code = str_replace($matches[0][$i], "<h4><a class='environment-link' href='" . getTagWithLabel($label) . "'>" . getIDWithLabel($label) . ". " . $matches[1][$i] . "</a></h4>", $code);
  }

  // remove remaining labels
  $code = preg_replace("/\\\label\{[\w\-]+\}\n?/", "", $code);
  
  // remove \linebreak commands
  $code = preg_replace("/\\\linebreak(\[\d?\])?/", "", $code);

  // lines starting with % (tag 03NV for instance) should be removed
  $code = preg_replace("/\%[\w.]+/", "", $code);

  // these do not fit into the system above
  $code = str_replace("\\begin{center}\n", "<center>", $code);
  $code = str_replace("\\end{center}", "</center>", $code);
  
  $code = str_replace("\\begin{quote}", "<blockquote>", $code);
  $code = str_replace("\\end{quote}", "</blockquote>", $code);

  // proof environment
  $code = str_replace("\\begin{proof}\n", "<p><strong>Proof.</strong> ", $code);
  $code = preg_replace("/\\\begin\{proof\}\[(" . $regex . ")\]/u", "<p><strong>$1</strong> ", $code);
  $code = str_replace("\\end{proof}", "<span style='float: right;'>$\square$</span></p>", $code);

  // hyperlinks
  $code = preg_replace("/\\\href\{(.*)\}\{(" . $regex . ")\}/u", "<a href=\"$1\">$2</a>", $code);
  $code = preg_replace("/\\\url\{(.*)\}/", "<a href=\"$1\">$1</a>", $code);

  // emphasis
  $code = preg_replace("/\{\\\it (" . $regex . ")\}/u", "<em>$1</em>", $code);
  $code = preg_replace("/\{\\\bf (" . $regex . ")\}/u", "<strong>$1</strong>", $code);
  $code = preg_replace("/\{\\\em (" . $regex . ")\}/u", "<em>$1</em>", $code);
  $code = preg_replace("/\\\emph\{(" . $regex . ")\}/u", "<em>$1</em>", $code);

  // footnotes
  $code = preg_replace("/\\\\footnote\{(" . $regex . ")\}/u", " ($1)", $code);

  // handle citations
  $count = preg_match_all("/\\\cite\{([\.\w\-\_]*)\}/", $code, $matches);
  for ($i = 0; $i < $count; $i++) {
    $item = getBibliographyItem($matches[1][$i]);
    $code = str_replace($matches[0][$i], '[<a title="' . parseTeX($item['author']) . ', ' . parseTeX($item['title']) . '" href="' . href('bibliography/' . $matches[1][$i]) . '">' . $matches[1][$i] . "</a>]", $code);
  }
  $count = preg_match_all("/\\\cite\[(" . $regex . ")\]\{([\w-]*)\}/", $code, $matches);
  for ($i = 0; $i < $count; $i++) {
    $item = getBibliographyItem($matches[2][$i]);
    $code = str_replace($matches[0][$i], '[<a title="' . parseTeX($item['author']) . ', ' . parseTeX($item['title']) . '" href="' . href('bibliography/' . $matches[2][$i]) . '">' . $matches[2][$i] . "</a>, " . $matches[1][$i] . "]", $code);
  }
  // TODO the use of the parseTeX routine should be checked

  // filter \input{chapters}
  $code = str_replace("\\input{chapters}", "", $code);

  // enumerates etc.
  $code = str_replace("\\begin{enumerate}\n", "<ol>", $code);
  $code = str_replace("\\end{enumerate}", "</ol>", $code);
  $code = str_replace("\\begin{itemize}\n", "<ul>", $code);
  $code = str_replace("\\end{itemize}", "</ul>", $code);
  $code = preg_replace("/\\\begin{list}(.*)\n/", "<ul>", $code); // unfortunately I have to ignore information in here
  $code = str_replace("\\end{list}", "</ul>", $code);
  $code = preg_replace("/\\\item\[(" . $regex . ")\]/u", "<li>", $code);
  $code = str_replace("\\item", "<li>", $code);

  // let HTML be aware of paragraphs
  $code = str_replace("\n\n", "</p><p>", $code);
  $code = str_replace("\\smallskip", "", $code);
  $code = str_replace("\\medskip", "", $code);
  $code = str_replace("\\noindent", "", $code);

  // parse references
  //$code = preg_replace('/\\\ref\{(.*)\}/', "$1", $code);
  $references = array();

  // don't escape in math mode because XyJax doesn't like that, and fix URLs too
  $lines = explode("\n", $code);
  $math_mode = false;
  foreach ($lines as &$line) {
    // $$ is a toggle
    if ($line == '$$')
      $math_mode = !$math_mode;
    $environments = array('equation', 'align', 'align*', 'eqnarray', 'eqnarray*');
    foreach ($environments as $environment) {
      if ($line == '\begin{' . $environment . '}') $math_mode = true;
      if ($line == '\end{' . $environment . '}') $math_mode = false;
    }

    if ($math_mode) {
      $line = str_replace('&gt;', '>', $line);
      $line = str_replace('&lt;', '<', $line);
      $line = str_replace('&amp;', '&', $line);
      
      $count = preg_match_all('/\\\ref{<a href=\"([\w\/]+)\">([\w-]+)<\/a>}/', $line, $matches);
      for ($j = 0; $j < $count; $j++) {
        $line = str_replace($matches[0][$j], getID(substr($matches[1][$j], -4)), $line);
      }
    }
  }
  $code = implode("\n", $lines);
  
  $count = preg_match_all('/\\\ref{<a href=\"([\w\/]+)\">([\w-]+)<\/a>}/', $code, $references);
  for ($i = 0; $i < $count; ++$i) {
    $code = str_replace($references[0][$i], "<a href='" . $references[1][$i] . "'>" . getID(substr($references[1][$i], -4, 4)) . "</a>", $code);
  }

  // fix macros
  $macros = array(
    // TODO check \mathop in output
    "\\lim" => "\mathop{\\rm lim}\\nolimits",
    "\\colim" => "\mathop{\\rm colim}\\nolimits",
    "\\Spec" => "\mathop{\\rm Spec}",
    "\\Hom" => "\mathop{\\rm Hom}\\nolimits",
    "\\SheafHom" => "\mathop{\mathcal{H}\!{\it om}}\\nolimits",
    "\\Sch" => "\\textit{Sch}",
    "\\Mor" => "\mathop{\\rm Mor}\\nolimits",
    "\\Ob" => "\mathop{\\rm Ob}\\nolimits",
    "\\Sh" => "\mathop{\\textit{Sh}}\\nolimits",
    "\\NL" => "\mathop{N\!L}\\nolimits");
  $code = str_replace(array_keys($macros), array_values($macros), $code);

  return $code;
}

function getEnclosingTag($position) {
  assert(positionExists($position));

  global $database;

  try {
    $sql = $database->prepare("SELECT tag, type, book_id FROM tags WHERE position < :position AND active = 'TRUE' AND type != 'item' AND TYPE != 'equation' ORDER BY position DESC LIMIT 1");
    $sql->bindParam(":position", $position);

    if ($sql->execute())
      return $sql->fetch();
    // TODO error handling
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  // TODO this should do more
  return "ZZZZ";
}

function getID($tag) {
  assert(isValidTag($tag));

  global $database;
  try {
    $sql = $database->prepare('SELECT book_id FROM tags WHERE tag = :tag');
    $sql->bindParam(':tag', $tag);

    if ($sql->execute())
      return $sql->fetchColumn();
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  return "";
}

function getIDWithLabel($label) {
  assert(labelExists($label));

  global $database;
  try {
    $sql = $database->prepare('SELECT book_id FROM tags WHERE label = :label AND active = "TRUE"');
    $sql->bindParam(':label', $label);

    if ($sql->execute())
      return $sql->fetchColumn();
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  return "ZZZZ";
}

function getTagAtPosition($position) {
  assert(positionExists($position));

  global $database;

  try {
    $sql = $database->prepare("SELECT tag, label FROM tags WHERE position = :position AND active = 'TRUE'");
    $sql->bindParam(":position", $position);

    if ($sql->execute())
      return $sql->fetch();
    // TODO error handling
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  // TODO more
  return "ZZZZ";
}

function getTagWithLabel($label) {
  assert(labelExists($label));

  global $database;
  try {
    $sql = $database->prepare('SELECT tag FROM tags WHERE label = :label AND active = "TRUE"');
    $sql->bindParam(':label', $label);

    if ($sql->execute())
      return $sql->fetchColumn();
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  return "ZZZZ";
}

function isValidTag($tag) {
  return (bool) preg_match_all('/^[[:alnum:]]{4}$/', $tag, $matches) == 1;
}

function labelExists($label) {
  global $database;
  try {
    $sql = $database->prepare('SELECT COUNT(*) FROM tags WHERE label = :label');
    $sql->bindParam(':label', $label);

    if ($sql->execute())
      return intval($sql->fetchColumn()) > 0;
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  return false;
}

function positionExists($position) {
  global $database;

  try {
    $sql = $database->prepare("SELECT COUNT(*) FROM tags WHERE position = :position AND active = 'TRUE'");
    $sql->bindParam(":position", $position);

    if ($sql->execute())
      return intval($sql->fetchColumn()) > 0;
    // TODO error handling
  }
  catch(PDOException $e) {
    echo $e->getMessage();
  }

  return false;
}

?>
<?php
/*
 *
 * Copyright (c) 2017 - Jiujie
 * @author xiaofeng.lu <513072539@qq.com>
 * @version 2.1
 * @file shuffle_answers.php
 * @description  处理sql打乱对应的数据信息导入数据
 */
// 读取 SQL 文件
$file = __DIR__ . '/ls_examine_question.txt';
if (!file_exists($file)) {
    exit("请确认 file = {$file} 是否存在呢，目前无法找到，请导入到当前目录再执行");
}
$content = file_get_contents($file);

// 正则匹配 INSERT 语句
// 格式: INSERT INTO `ls_examine_question` VALUES (id, types, 'title', 'options', 'answer', grade, select, 'topic');
// 注意处理 options 中的 JSON 和可能存在的转义引号
$pattern = "/INSERT INTO `ls_examine_question` VALUES \((.*?)\);/";

$newContent = preg_replace_callback($pattern, function ($matches) {
    $valueStr = $matches[1];

    // 简单的 CSV 解析往往处理不了 JSON 中的逗号和引号，我们需要更健壮的解析
    // 这里手动解析 values
    $values = parseValues($valueStr);

    if (count($values) < 8) {
        return $matches[0]; // 解析失败，保持原样
    }

    $id = $values[0];
    $types = $values[1];
    $title = $values[2];
    $optionJson = $values[3];
    $answer = $values[4]; // '1', '2', '3', '4'
    $grade = $values[5];
    $select = $values[6];
    $topic = $values[7];

    // 解码选项
    // 去除首尾的单引号，处理转义
    $jsonStr = stripslashes(trim($optionJson, "'"));
    $options = json_decode($jsonStr, true);

    if (!is_array($options) || empty($options)) {
        return $matches[0];
    }

    // 获取正确答案的文本
    $answerIndex = intval(trim($answer, "'")) - 1; // 0-based index
    if (!isset($options[$answerIndex])) {
        return $matches[0];
    }
    $correctOptionText = $options[$answerIndex];

    // 打乱选项顺序
    shuffle($options);

    // 找到正确答案的新位置
    $newAnswerIndex = array_search($correctOptionText, $options);
    $newAnswer = $newAnswerIndex + 1; // 1-based index

    // 重新编码选项
    // JSON_UNESCAPED_UNICODE 保持中文不被转义
    $newOptionJson = json_encode($options, JSON_UNESCAPED_UNICODE);
    // 重新转义单引号，用于 SQL
    $newOptionJsonSql = "'" . addslashes($newOptionJson) . "'";

    // 重新构建 VALUES 字符串
    // 保持其他字段原样，只更新 option 和 answer
    $newValues = [
        $id,
        $types,
        $title,
        $newOptionJsonSql,
        "'" . $newAnswer . "'",
        $grade,
        $select,
        $topic
    ];

    return "INSERT INTO `ls_examine_question` VALUES (" . implode(", ", $newValues) . ");";

}, $content);

// 写入新文件
file_put_contents($file, $newContent);

echo "Done. Processed file: $file\n";

/**
 * 简易 SQL VALUES 解析器
 * 处理带引号字符串（包括转义引号）和数字
 */
function parseValues($str)
{
    $values = [];
    $length = strlen($str);
    $buffer = '';
    $inQuote = false;
    $quoteChar = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $str[$i];

        if ($inQuote) {
            if ($char === '\\') {
                $buffer .= $char;
                $i++; // Skip next char
                if ($i < $length) $buffer .= $str[$i];
            } elseif ($char === $quoteChar) {
                $inQuote = false;
                $buffer .= $char;
            } else {
                $buffer .= $char;
            }
        } else {
            if ($char === "'" || $char === '"') {
                $inQuote = true;
                $quoteChar = $char;
                $buffer .= $char;
            } elseif ($char === ',') {
                $values[] = trim($buffer);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
    }
    if ($buffer !== '') {
        $values[] = trim($buffer);
    }
    return $values;
}

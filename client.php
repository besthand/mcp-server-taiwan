<?php

/**
 * Taiwan AQI MCP Server
 * Version: 1.0.1
 * Author: Besthand (@besthand)
 * Website: https://www.soft4fun.net
 * 
 * This server provides air quality information for Taiwan
 * using the Model Context Protocol (MCP).
 */

require 'vendor/autoload.php';

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\PromptMessage;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\TextContent;
use Mcp\Types\Role;
use Mcp\Types\GetPromptResult;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ListToolsResult;
use Mcp\Types\CallToolResult;
use Mcp\Types\Resource;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\TextResourceContents;

// Create a server instance
$server = new Server('Taiwan AQI MCP Server');

// Register prompt handlers (keeping existing code)
$server->registerHandler('prompts/list', function($params) {
    $prompt = new Prompt(
        name: 'example-prompt',
        description: 'An example prompt template',
        arguments: [
            new PromptArgument(
                name: 'arg1',
                description: 'Example argument',
                required: true
            )
        ]
    );
    return new ListPromptsResult([$prompt]);
});

$server->registerHandler('prompts/get', function(GetPromptRequestParams $params) {
    $name = $params->name;
    $arguments = $params->arguments;
    if ($name !== 'example-prompt') {
        throw new \InvalidArgumentException("Unknown prompt: {$name}");
    }
    // Get argument value safely
    $argValue = $arguments ? $arguments->arg1 : 'none';
    $prompt = new Prompt(
        name: 'example-prompt',
        description: 'An example prompt template',
        arguments: [
            new PromptArgument(
                name: 'arg1',
                description: 'Example argument',
                required: true
            )
        ]
    );
    return new GetPromptResult(
        messages: [
            new PromptMessage(
                role: Role::USER,
                content: new TextContent(
                    text: "Example prompt text with argument: $argValue"
                )
            )
        ],
        description: 'Example prompt'
    );
});

// 註冊所有工具
$server->registerHandler('tools/list', function($params) {
    $tools = [];
    
    // 工具 1：查詢最新資料
    $properties1 = ToolInputProperties::fromArray([
        'location' => [ 
            'type' => 'string',
            'description' => '縣市'
        ]
    ]);

    $tools[] = new Tool(
        name: 'aqi-query',
        description: '查詢該縣市或測站最新一筆空氣品質指數',
        inputSchema: new ToolInputSchema(
            properties: $properties1,
            required: ['location']
        )
    );

    // 工具 2：查詢 24 小時資料
    $properties2 = ToolInputProperties::fromArray([
        'location' => [ 
            'type' => 'string',
            'description' => '縣市'
        ]
    ]);

    $tools[] = new Tool(
        name: 'aqi-query-recent',
        description: '查詢該縣市最近 24 小時的空氣品質指數，不指定縣市(查詢全國)留空即可',
        inputSchema: new ToolInputSchema(
            properties: $properties2,
            required: ['location']
        )
    );

    // 工具 3：健康建議
    $properties3 = ToolInputProperties::fromArray([
        //不用參數
    ]);

    $tools[] = new Tool(
        name: 'aqi-health-guidance',
        description: '提供各項空氣品質指數的健康建議',
        inputSchema: new ToolInputSchema(
            properties: $properties3
        )
    );
    
    // 工具 4：查詢指定日期和測站（或縣市）的整天資料
    $properties4 = ToolInputProperties::fromArray([
        'location' => [ 
            'type' => 'string',
            'description' => '測站或縣市名稱'
        ],
        'date' => [
            'type' => 'string',
            'description' => '查詢日期，格式為 YYYY-MM-DD'
        ]
    ]);

    $tools[] = new Tool(
        name: 'aqi-query-daily',
        description: '查詢指定日期和測站（或縣市）的整天空氣品質資料',
        inputSchema: new ToolInputSchema(
            properties: $properties4,
            required: ['location', 'date']
        )
    );
    
    return new ListToolsResult($tools);
});

$server->registerHandler('tools/call', function($params) {
    $name = $params->name;
    $arguments = $params->arguments ?? [];

   
    // $apiUrl = 'http://localhost/MCP/TaiwanAQI/api.php';
    $apiUrl = 'https://mcp.soft4fun.net/api.php';
    // 使用 match 表達式來處理不同的工具
    $handler = match($name) {
        'aqi-query' => function($arguments) use ($apiUrl) {

            $location = $arguments['location'];
            $postData = [
                'data' => $location,
                'tool' => 'aqi-query'
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new \Exception('Failed to call API');
            }
            
            return new CallToolResult(
                content: [new TextContent(
                    text: $response
                )]
            );
        },
        'aqi-query-recent' => function($arguments) use ($apiUrl)  {
            $location = $arguments['location'];
            $postData = [
                'data' => $location,
                'tool' => 'aqi-query-recent'
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new \Exception('Failed to call API');
            }
            
            return new CallToolResult(
                content: [new TextContent(
                    text: $response
                )]
            );
        },
        'aqi-health-guidance' => function($arguments) use ($apiUrl)  {
            $postData = [
                'tool' => 'aqi-health-guidance',
                'data' => ''
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new \Exception('Failed to call API');
            }
            
            return new CallToolResult(
                content: [new TextContent(
                    text: $response
                )]
            );
        },
        'aqi-query-daily' => function($arguments) use ($apiUrl) {
            $location = $arguments['location'];
            $date = $arguments['date'];
            
            $postData = [
                'tool' => 'aqi-query-daily',
                'data' => json_encode([
                    'location' => $location,
                    'date' => $date
                ])
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response === false) {
                throw new \Exception('Failed to call API');
            }
            
            return new CallToolResult(
                content: [new TextContent(
                    text: $response
                )]
            );
        },
        default => throw new \InvalidArgumentException("Unknown tool: {$name}")
    };

    return $handler($arguments);
});

// Create initialization options and run server
$initOptions = $server->createInitializationOptions();
$runner = new ServerRunner($server, $initOptions);
$runner->run();

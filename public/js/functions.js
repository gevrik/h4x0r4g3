function showPrompt()
{
    if (consoleMode === 'default')
    {
        var jsonData;
        jsonData = {
            command: 'showPrompt',
            hash: hash,
            content: 'default'
        };
        conn.send(JSON.stringify(jsonData));
    }
}

/**
 * Reset the mail mode console options.
 */
function resetConsoleOptionsMail()
{
    consoleOptionsMail = {
        currentMailNumber: 0
    };
}

/**
 * Reset the code mode console options.
 */
function resetConsoleOptionsCode()
{
    consoleOptionsCode = {
        mode: 'resource',
        fileType: 0,
        fileLevel: 0
    };
}
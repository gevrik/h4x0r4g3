function showprompt()
{
    if (consoleMode === 'default')
    {
        var jsonData;
        jsonData = {
            command: 'showprompt',
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

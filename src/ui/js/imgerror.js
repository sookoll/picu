

function imgerror(e)
{
    setTimeout(reloadImg, 1000, e);
}

function reloadImg(e)
{
    const source = e.src;
    e.removeAttribute('onerror');
    e.src = source;
}

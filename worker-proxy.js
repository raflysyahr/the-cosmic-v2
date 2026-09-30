export default {
  async fetch(request) {
    const url = new URL(request.url)
    const path = url.pathname.replace('/api/proxy/', '')
    const params = url.searchParams

    let target, headers

    if (path === 'image' && params.get('url')) {
      target = params.get('url')
      headers = {
        'Referer': 'https://v3.komikcast.fit/',
        'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Mobile Safari/537.36',
        'Accept': 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
        'sec-ch-ua': '"Not A(Brand";v="8", "Chromium";v="132"',
        'sec-ch-ua-mobile': '?1',
        'sec-ch-ua-platform': '"Android"',
      }
    } else {
      target = 'https://be.komikcast.cc/' + path + (params.toString() ? '?' + params.toString() : '')
      headers = {
        'Referer': 'https://v3.komikcast.fit/',
        'User-Agent': 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Mobile Safari/537.36',
        'Accept': 'application/json',
        'sec-ch-ua': '"Not A(Brand";v="8", "Chromium";v="132"',
        'sec-ch-ua-mobile': '?1',
        'sec-ch-ua-platform': '"Android"',
      }
      const auth = request.headers.get('Authorization')
      if (auth) headers['Authorization'] = auth
    }

    const body = request.method === 'GET' || request.method === 'HEAD' ? null : await request.text()
    const contentType = request.headers.get('Content-Type') || 'application/json'

    const cfResponse = await fetch(target, {
      method: request.method,
      headers: {
        ...headers,
        ...(body ? { 'Content-Type': contentType } : {}),
      },
      body,
    })

    const responseHeaders = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
      'Cache-Control': 'public, max-age=30',
    }

    const respContentType = cfResponse.headers.get('Content-Type') || 'application/json'
    responseHeaders['Content-Type'] = respContentType

    return new Response(cfResponse.body, {
      status: cfResponse.status,
      headers: responseHeaders,
    })
  },
}

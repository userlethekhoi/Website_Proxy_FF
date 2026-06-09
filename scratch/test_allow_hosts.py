from mitmproxy import ctx

def running():
    print("Mitmproxy running hook triggered!")
    print("Current allow_hosts:", ctx.options.allow_hosts)
    ctx.options.allow_hosts = [r"freefiremobile\.com"]
    print("Updated allow_hosts:", ctx.options.allow_hosts)

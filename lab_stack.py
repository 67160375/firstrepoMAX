class Stack:
    def __init__(self):
        self.items = []

    def is_empty(self):
        return not self.items

    def push(self, item):
        self.items.append(item)

    def pop(self):
        if self.is_empty():
            return "Stack is empty!"
        return self.items.pop()

    def peek(self):
        if self.is_empty():
            return "Stack is empty!"
        return self.items[-1]

    def size(self):
        return len(self.items)



s = Stack()

print("Is empty?", s.is_empty())

s.push(1)
s.push(2)
s.push(3)
s.push(4)
s.push(5)

print("Size after push:", s.size())
print("Top element:", s.peek())

print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())
print("Pop:", s.pop())

print("Is empty?", s.is_empty())

print("Pop from empty:", s.pop())
